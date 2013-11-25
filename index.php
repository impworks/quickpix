<?php

// ------------------------------------
//  a tiny picture gallery by impworks
//
// https://github.com/impworks/quickpix
// ------------------------------------

define('ITEMS_IN_ROW', 4);
define('ROOT_DIR', '/');
define('TITLE', 'QuickPix');

class qp
{
    private $dirs;
    private $files;
    private $all_dirs;
    private $setup;

    // load directory file
    function load_dirs($from, &$data)
    {
        $file = $from . "/.dirs";
        if (file_exists($file))
        {
            $tmp = file($file);
            foreach ($tmp as $nm => $val)
            {
                $data[$nm] = explode("::", $val);
                $sub = $from . '/' . $data[$nm][0] . '/' . '.dirs';
                if (file_exists($sub))
                    $this->load_dirs($from . '/' . $data[$nm][0], $data[$nm][3]);
            }
        }
    }

    // determine mode
    function determine_mode()
    {
        $q = $_SERVER['REDIRECT_QUERY_STRING'];

        if (preg_match('/^(.*?)\/(.*?)\.s$/i', $q))
            $mode = 'small';

        elseif (preg_match('/^(.*?)\/(.*?)\.m$/i', $q))
            $mode = 'med';

        elseif (preg_match('/^(.*?)\/(.*?)\.view$/i', $q))
            $mode = 'view';

        elseif (preg_match('/^(.*?)\.update$/i', $q))
            $mode = 'update';

        elseif (preg_match('/^(.*?)\/[^\/]+\.zip$/i', $q))
            $mode = 'zip';

        elseif (preg_match('/^(.*?)\/?$/i', $q))
            $mode = 'dir';

        else
            $mode = 'wtf';

        if (!$q) $q = '.';
        if ($q[strlen($q) - 1] == '/') $q = substr($q, 0, -1);
        $q = str_replace('..', '', $q);
        $this->setup = array('mode' => 'qp_' . $mode, 'query' => $q);
    }

    function breadcrumb($query)
    {
        $pieces = explode("/", $query);
        $folder = $this->all_dirs;
        $crumb = '<a href="http://' . $_SERVER['HTTP_HOST'] . ROOT_DIR . '/">' . TITLE . '</a>';
        $path = '';
        for ($idx = 0; $idx < count($pieces); $idx++)
        {
            if (!$folder)
                continue;

            foreach ($folder as $nm => $val)
            {
                if (!strcasecmp($val[0], $pieces[$idx]))
                {
                    $path .= '/' . $val[0];
                    $crumb .= ' &raquo; <a href="http://' . $_SERVER['HTTP_HOST'] . ROOT_DIR . $path . '/">' . (trim($val[2]) ? trim($val[2]) : $val[0]) . '</a>';
                    $folder = $val[3];
                    break;
                }
            }
        }

        return $crumb;
    }

    function make_pic($size, $src, $dest)
    {
        list($x, $y) = getimagesize($src);
        $img = imagecreatefromjpeg($src);

        // detect size
        if ($x > $y)
        {
            $newx = $size;
            $newy = $y * $size / $x;
        }
        else
        {
            $newy = $size;
            $newx = $x * $size / $y;
        }

        $imgdest = imagecreatetruecolor($newx, $newy);
        imagecopyresampled($imgdest, $img, 0, 0, 0, 0, $newx, $newy, $x, $y);
        imagejpeg($imgdest, $dest);
    }

    function qp_zip($query)
    {
        $matches = array ();
        $result = preg_match('/^(?<folder>.*)\/[^\/]+\.zip$/im', $query, $matches);
        if(!$result)
            return '<div class="errormsg">Directory not found!</div>';

        $folder = $matches['folder'] . '/';
        $folderinfo = pathinfo($folder);
        $foldername = $folderinfo['basename'];

        if (is_dir($folder))
        {
            $this->scan_files($folder);

            $zip = new ZipArchive;
            $zip->open($folder . $foldername . '.zip', ZipArchive::CREATE);

            foreach ($this->files as $nm => $val)
                $zip->addFile($folder . $val[0]);

            $zip->close();

            header('Location: ' . $foldername . '.zip');
        }
    }

    function qp_update($query)
    {
        $matches = array ();
        $result = preg_match('/^(?<folder>.*)\/\.update$/im', $query, $matches);
        $folder = $result ? $matches['folder'] : '.';

        if (is_dir($folder))
        {
            //update directories
            $file = $folder . '/.dirs';
            if (file_exists($file))
            {
                $dirs = file($file);
                foreach ($dirs as $nm => $val)
                    $dirs[$nm] = explode("::", trim($val));
            }

            $content = "";
            if ($handle = opendir($folder))
            {
                while (($curr = readdir($handle)) !== false)
                {
                    $temp = $folder . '/' . $curr;
                    if (is_dir($temp) && $curr != '.' && $curr != '..')
                    {
                        // check if .hidden file is not present
                        if (!file_exists($temp . '/.hidden'))
                        {
                            $oldname = '';
                            foreach ($dirs as $nm => $val)
                            {
                                if (!strcasecmp($val[0], $curr))
                                {
                                    $oldname = $val[2];
                                    break;
                                }
                            }

                            $content .= $curr . '::' . $this->count_files($folder . '/' . $curr) . '::' . $oldname . "\n";
                        }
                    }
                }
            }

            file_put_contents($file, $content);

            // update files
            $file = $folder . '/.files';
            if (file_exists($file))
            {
                $files = file($file);
                foreach ($files as $nm => $val)
                    $files[$nm] = explode("::", trim($val));
            }

            $content = "";
            if ($handle = opendir($folder))
            {
                while (($curr = readdir($handle)) !== false)
                {
                    if (preg_match('/(?<!folder)\.jpg$/i', $curr))
                    {
                        $oldname = '';
                        foreach ($files as $nm => $val)
                        {
                            if (!strcasecmp($val[0], $curr))
                            {
                                $oldname = $val[1];
                                break;
                            }
                        }

                        $content .= $curr . '::' . $oldname . "\n";
                    }
                }
            }

            file_put_contents($file, $content);
        }

        header("Location: .");
    }

    function qp_dir($query)
    {
        if (!is_dir($query))
            return '<div class="errormsg">Directory not found!</div>';

        $this->scan_dirs($query);
        $this->scan_files($query);

        $return = '';

        if ($this->dirs)
        {
            $return .= '
          <div class="block">
            <table cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td class="header">Subfolders</td>
              </tr>
              <tr>
                <td class="text cell">
                  <table cellpadding="0" cellspacing="0" border="0" width="100%">';

            $items = count($this->dirs);
            $rows = ceil($items / ITEMS_IN_ROW);
            $space = 100 / ITEMS_IN_ROW;
            $curr = 0;
            for ($idx = 0; $idx < $rows; $idx++)
            {
                $return .= '<tr>';

                for ($idx2 = 0; $idx2 < ITEMS_IN_ROW; $idx2++)
                {
                    $dir = $this->dirs[$curr];
                    $return .= '<td class="cell text" width="' . $space . '%" align="center" valign="middle">';

                    if ($curr < $items)
                        $return .= '<div class="folder">' . ($dir[1] ? '<a href="' . $dir[0] . '/">' . $dir[1] . '</a>' : '') . '</div><br><a href="' . $dir[0] . '/">' . (trim($dir[2]) ? $dir[2] : $dir[0]) . '</a>';

                    $return .= '</td>';

                    $curr++;
                }

                $return .= '</tr>';
            }

            $return .= '
                  </table>
                </td>
              </tr>
            </table>
          </div>';
        }

        if ($this->files)
        {
            if ($this->dirs) $return .= '<br><br>';

            $dirinfo = pathinfo($query);
            $dirname = $dirinfo['basename'];

            $return .= '
          <div class="block">
            <table cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td class="header">' . $this->breadcrumb($query) . '</td>
                <td class="header" align="right" width="20%"><a href="' . $dirname . '.zip">Download as archive</a></td>
              </tr>
              <tr>
                <td class="text cell" colspan="2">
                  <table cellpadding="12" cellspacing="0" border="0" width="100%">';

            $items = count($this->files);
            $rows = ceil($items / ITEMS_IN_ROW);
            $curr = 0;
            for ($idx = 0; $idx < $rows; $idx++)
            {
                $return .= '<tr>';

                for ($idx2 = 0; $idx2 < ITEMS_IN_ROW; $idx2++)
                {
                    $return .= '<td class="text" width="25%" align="center" valign="middle">';
                    if ($curr < $items)
                    {
                        $filename = substr($this->files[$curr][0], 0, -4);
                        $return .= '<a href="' . $filename . '.view"><img src="' . $filename . '.s" border="0"><br>' . $this->files[$curr][0] . '</a>';
                    }
                    $return .= '</td>';

                    $curr++;
                }

                $return .= '</tr>';
            }

            $return .= '
                  </table>
                </td>
              </tr>
            </table>
          </div>';
        }

        if (!$this->dirs && !$this->files)
            return '<div class="errormsg">Directory is empty!</div>';

        return $return;
    }

    function qp_view($query)
    {
        $file = $this->fix_case(str_replace(".view", ".jpg", $query));

        if (!file_exists($file))
            return '<div class="errormsg">File does not exist!</div>';

        $matches = array();
        preg_match('/(?<folder>.*)\/(?<file>.*?)\.jpg$/im', $file, $matches);
        $folder = $matches['folder'];
        $filename = $matches['file'];
        $this->scan_files($folder);

        $return = '';
        for ($idx = 0; $idx < count($this->files); $idx++)
        {
            $curr = $this->files[$idx];
            if (!strcasecmp($curr[0], $filename . '.jpg'))
            {
                $descr = trim($curr[1]);

                if ($idx > 0)
                    $prev = substr($this->files[$idx - 1][0], 0, -4);
                if ($idx < count($this->files) - 1)
                    $next = substr($this->files[$idx + 1][0], 0, -4);

                break;
            }
        }

        $return .= '
  <div class="block">
    <table cellpadding="0" cellspacing="0" border="0" width="100%">
      <tr>
        <td class="header">' . $this->breadcrumb($folder) . ' &raquo; ' . $filename . '</td>
      </tr>
      <tr>
        <td class="text cell" align="center" valign="center"><br>
          <a href="' . $this->fix_case($query) . '"><img src="' . $filename . '.m" border="0" title="View full picture"></a><br><br>
          <table cellpadding="10" cellspacing="0" border="1" class="pic-info" bordercolor="#999999">
            <tr>
              <td class="text" width="120" align="center">' . ($prev ? '<nobr>&laquo; <a title="Previous picture" id="link_prev" href="' . $prev . '.view">' . $prev . '.jpg</a></nobr>' : '&nbsp;') . '</td>
              <td class="text" width="400" align="center">' . $descr . '</td>
              <td class="text" width="120" align="center">' . ($next ? '<nobr><a title="Next picture" id="link_next" href="' . $next . '.view">' . $next . '.jpg</a> &raquo;</nobr>' : '&nbsp;') . '</td>
            </tr>
          <table>
        </td>
      </tr>
    </table>
  </div>';

        return $return;
    }

    function qp_med($query)
    {
        $dest = $this->fix_case($query);
        $src = $this->fix_case(str_replace('.m', '.jpg', $query));
        if (!file_exists($dest))
            $this->make_pic(640, $src, $dest);

        return $this->qp_file_generic($dest);
    }

    function qp_small($query)
    {
        $dest = $this->fix_case($query);
        $src = $this->fix_case(str_replace('.s', '.jpg', $query));
        if (!file_exists($dest))
            $this->make_pic(120, $src, $dest);

        return $this->qp_file_generic($dest);
    }

    function qp_file_generic($file)
    {
        if (!file_exists($file))
            return '<div class="errormsg">File does not exist!</div>';

        header("Content-Type: image/jpeg");
        readfile($file);
        exit;
    }

    function qp_wtf($query)
    {
        return '<div class="errormsg"><marquee><span style="font-size: 72px;">WUT?!&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#3232;___&#3232;</span></marquee></div>';
    }

    function scan_files($dir)
    {
        $file = $dir . '/.files';
        if (!file_exists($file))
        {
            $content = "";

            if (is_dir($dir))
                if ($handle = opendir($dir))
                    while (($curr = readdir($handle)) !== false)
                         $content .= $curr . "::\n";

            file_put_contents($file, $content);
        }

        if (file_exists($file))
        {
            $tmp = file($file);
            foreach ($tmp as $nm => $val)
                $this->files[$nm] = explode("::", $val);
        }
        else
            $this->files = false;
    }

    function scan_dirs($dir)
    {
        $file = $dir . '/.dirs';
        if (!file_exists($file))
        {
            $content = "";
            if (is_dir($dir))
            {
                if ($handle = opendir($dir))
                {
                    while (($curr = readdir($handle)) !== false)
                    {
                        $temp = $dir . '/' . $curr;
                        if (is_dir($temp) && $curr != '.' && $curr != '..')
                            $content .= $curr . '::' . $this->count_files($dir . '/' . $curr) . "::\n";
                    }
                }

                file_put_contents($file, $content);
            }
        }

        if (file_exists($file))
        {
            $tmp = file($file);
            foreach ($tmp as $nm => $val)
                $this->dirs[$nm] = explode("::", $val);
        }
        else
            $this->files = false;
    }

    function count_files($dir)
    {
        if (file_exists($dir . '/.files'))
            return count(file($dir . '/.files'));

        $count = 0;

        if ($handle = opendir($dir))
            while (($curr = readdir($handle)) !== false)
                if (preg_match('/(?<!folder)\.jpg$/i', $curr))
                    $count++;

        return $count;
    }

    function output_tree($root, $data)
    {
        if (is_array($data) && count($data))
        {
            foreach ($data as $nm => $val)
            {
                echo '<div class="pck-header pck-' . ($data == $this->all_dirs ? '' : 'sub') . 'branch"><div class="pck-menu">' . ($val[1] > 0 ? $val[1] : '') . '</div><a href="http://' . $_SERVER['HTTP_HOST'] . '/' . $root . $val[0] . '/' . '">' . (trim($val[2]) ? trim($val[2]) : $val[0]) . (is_array($val[3]) ? ' &rarr;' : '') . '</a></div>';
                if (is_array($val[3]))
                {
                    echo '<div class="pck-sub pck-hidden">';
                    $this->output_tree($root . $val[0] . '/', $val[3]);
                    echo '</div>';
                }
            }
        }
        else
        {
            echo '<div class="errormsg">Directory is empty!</div>';
        }
    }

    function fix_case($path)
    {
        $info = pathinfo($path);
        $base = $info['dirname'];
        $files = scandir($base);
        foreach($files as $file)
        {
            $curr = $base . '/' . $file;
            if(strcasecmp($curr, $path) == 0)
                return $curr;
        }

        return $path;
    }

    // output
    function output($result)
    {
        // head
        echo '
    
<html>
  <head>
    <title>' . TITLE . '</title>
    <meta http-equiv="Content-Language" content="ru" />
    <meta http-equiv="Content-Type" content="text/html; charset=cp1251" />
    <script language="javascript">
    document.onkeydown = navigate;

    function navigate(event)
    {
      if(!document.getElementById) return;

      if(window.event) event = window.event;

      if(event.ctrlKey)
      {
        var link = null;
        var href = null;
        switch (event.keyCode ? event.keyCode : event.which ? event.which : null)
        {
          case 0x25:
            link = document.getElementById("link_prev");
            break;
          case 0x27:
            link = document.getElementById("link_next");
            break;
        }

        if (link && link.href) document.location = link.href;
        if (href) document.location = href;
      }     
    }

    </script>
    <style>
      
      .pck-header a
      {
        text-decoration: none;
        color: #000000;
        display: block;
        padding: 4px;
      }
      
      .block
      {
        border: 1px #666688 solid;
        padding: 0px;
        width: 100%;
        margin-bottom: 24px;
        display: block;
        position: relative;
      }

      .header
      {
        padding: 6px;
        font-family: Tahoma;
        font-size: 14px;  
        font-weight: bold;
        color: #000000;
        background-color: #DCD7B6;
      }

      .text
      {
        font-family: Verdana;
        font-size: 12px;
        color: #000000;
      }

      .cell
      {
        padding: 4px;
      }

      .smalltext
      {
        font-family: Verdana;
        font-size: 9px;
        color: #000000;
        background-color: #FFFFFF;
      }

      .okmsg
      {
        font-family: Verdana;
        font-size: 10px;
        color: #000000;
        background-color: #96FF96;
        border: 1px #029801 solid;
        padding: 6px;
      }

      .errormsg
      {
        font-family: Verdana;
        font-size: 10px;
        color: #000000;
        background-color: #FF8787;
        border: 1px #7E0101 solid;
        padding: 6px;
      }
      
      .pck-header
      {
        font-family: Verdana;
        font-size: 12px;
        cursor: pointer;
        display: block;
        white-space: nowrap;
        clear: both;
        margin-bottom: 2px;
      }
      
      .pck-menu
      {
        top: 4px;
        right: 4px;
        float: right;
        height: 18px;
        position: relative;
        opacity: 0.2;
        -moz-opacity: 0.2;
        filter:alpha(opacity=20);
      }

      .pck-sub
      {
        padding: 0px;
        padding-left: 16px;
        border: 0px;
      }

      .pck-branch
      {
        border: 1px #666688 solid;
        background-color: #9BB3D5;
      }
      
      .pck-subbranch
      {
        border: 1px #666688 solid;
        background-color: #C8D5E8;
      }
      
      .pck-hidden
      {
        display: none;
      }
      
      .pic-info
      {
        border-collapse: collapse;
        background-color: #ECE9D8;
        margin: 16px;
      }

      .folder
      {
        width: 120px;
        height: 120px;
        padding-top: 10px;
        border: 2px #CCCCCC solid;
        text-align: center;
        font-weight: bold;
        font-size: 24pt;
        color: #CCCCCC;
      }

      .folder a
      {
        color: #CCCCCC;
        text-decoration: none;
      }

    </style>
  </head>
  <body>
    <table style="width: 100%; border: 0px">
      <tr>
        <td align="center">
          <table style="width: 1024px; border: 0px" cellspacing="16">
            <tr>
              <td id="side" style="width: 250px; padding-right: 32px" valign="top">
              
              <div class="block">
                <table cellpadding="0" cellspacing="0" border="0" width="100%"> 
                  <tr>
                    <td class="header">Folders</td>
                  </tr>
                  <tr>
                    <td class="text cell">';

        // output trees
        $this->output_tree('', $this->all_dirs);

        echo '
                      </td>
                    </tr>
                  </table>
                </div>
              </td>
              <td id="content" valign="top">';

        echo $result;


        echo '
              </td>
            <tr>
          </table>
        </td>
      </tr>
    </table>';
    }

    // main function
    function process()
    {
        $this->load_dirs('.', $this->all_dirs);
        $this->determine_mode();
        $result = $this->{$this->setup['mode']}($this->setup['query']);
        $this->output($result);
    }
}

// OMG RUN!!!

$qp = new qp();
$qp->process();

?>