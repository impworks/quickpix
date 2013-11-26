<?php

// ------------------------------------
//  a tiny picture gallery by impworks
//
// https://github.com/impworks/quickpix
// ------------------------------------

/**
 * Number of picture thumbnails or folders in a row.
 */
define('ITEMS_IN_ROW', 4);

/**
 * The root directory of your gallery relative to server's root path.
 * For example, if your website is http://www.example.com and the gallery is at http://www.example.com/my/gallery,
 * the ROOT_DIR must be set to /my/gallery
 */
define('ROOT_DIR', '/');

/**
 * The caption of your gallery.
 * Displayed in <title> tag and breadcrumb root.
 */
define('TITLE', 'QuickPix');

/**
 * Checks if automatic folder data refresh can be performed by opening /foldername/.update.
 * Default = true.
 * Can be disabled in case of malicious requests.
 */
define('ALLOW_UPDATE', true);

/**
 * Checks if all generated cache files (thumbnails, archives, etc.) can be removed by opening /foldername/.clear.
 * Default = true.
 * Can be disabled in case of malicious requests.
 */
define('ALLOW_CLEAR', true);

/**
 * Checks if users are allowed to download entire directories as zip archives.
 * Zip archive is cached, therefore it requires additional space.
 * Default = true.
 */
define('ALLOW_ZIP', true);

class qp
{
    private $dirs;
    private $files;
    private $all_dirs;
    private $setup;

    /**
     * Loads data from a pre-cached .dirs file.
     *
     * @param $from string Path to directory.
     * @param $data array Referenced array to store data in.
     */
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

    /**
     * Dispatcher method that detects mode depending on query.
     */
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

    /**
     * Generates breadcrumb HTML code string for current folder and/or path.
     *
     * @param $query string Current query.
     * @return string HTML code.
     */
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

    /**
     * Creates a preview image and stores it on the disk.
     * The preview image is proportionally resized to fit into a 640x640 px square.
     *
     * @param $src string Path to source image.
     * @param $dest string Path to destination image.
     */
    function make_preview($src, $dest)
    {
        $size = 640;

        list($x, $y) = getimagesize($src);
        $imgsrc = imagecreatefromjpeg($src);

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
        imagecopyresampled($imgdest, $imgsrc, 0, 0, 0, 0, $newx, $newy, $x, $y);
        imagejpeg($imgdest, $dest);
    }

    /**
     * Creates a thumbnail image and stores it on the disk.
     * The thumbnail image is a 120x120 square cropped in the center of the original image.
     *
     * @param $src string Path to source image.
     * @param $dest string Path to destination image.
     */
    function make_thumb($src, $dest)
    {
        $size = 120;

        list($x, $y) = getimagesize($src);
        $imgsrc = imagecreatefromjpeg($src);

        $srcsize = min($x, $y);
        if($x > $y)
        {
            $yoff = 0;
            $xoff = (($x * $size / (float)$y) - $size) / 2;
        }
        else
        {
            $yoff = (($y * $size / (float)$x) - $size) / 2;
            $xoff = 0;
        }

        $imgdest = imagecreatetruecolor($size, $size);
        imagecopyresampled($imgdest, $imgsrc, 0, 0, $xoff, $yoff, $size, $size, $srcsize, $srcsize);
        imagejpeg($imgdest, $dest);
    }

    /**
     * Creates a zip archive with all files in the current directory.
     * Does not include subdirectories.
     *
     * @param $query string Path to archive.
     * @return mixed Error message or zip archive to download.
     */
    function qp_zip($query)
    {
        $matches = array ();
        $result = preg_match('/^(?<folder>.*)\/[^\/]+\.zip$/im', $query, $matches);
        if(!$result)
            return '<div class="errormsg">Directory not found!</div>';

        $folder = $matches['folder'] . '/';
        $folderinfo = pathinfo($folder);
        $foldername = $folderinfo['basename'];

        if(!ALLOW_ZIP)
        {
            header("Location: /" . $folder);
            exit;
        }

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

    /**
     * Updates the subfolder & file cache for a directory in case its content has been modified.
     *
     * @param $query Path to directory.
     */
    function qp_update($query)
    {
        if(!ALLOW_UPDATE)
        {
            header("Location: .");
            exit;
        }

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

    /**
     * Displays directory content.
     *
     * @param $query string Path to directory.
     * @return string HTML code to output.
     */
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
                <td class="header" align="right" width="20%">' . ( ALLOW_ZIP ? '<a href="' . $dirname . '.zip">Download as archive</a>' : '' ) . '</td>
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

    /**
     * Displays single file preview.
     *
     * @param $query string Path to file.
     * @return string HTML code to output.
     */
    function qp_view($query)
    {
        $file = $this->get_filename($query);

        if (!file_exists($file))
            return '<div class="errormsg">File does not exist!</div>';

        $matches = array();
        preg_match('/(?<folder>.*)\/(?<file>[^\/]*?)$/im', $file, $matches);
        $folder = $matches['folder'];
        $filename = $matches['file'];
        $this->scan_files($folder);

        $return = '';
        for ($idx = 0; $idx < count($this->files); $idx++)
        {
            $curr = $this->files[$idx];
            if (!strcasecmp($curr[0], $filename))
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
          <a href="' . $file . '"><img src="' . $filename . '.m" border="0" title="View full picture"></a><br><br>
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

    /**
     * Creates a preview and outputs it to the browser.
     *
     * @param $query string Path to image.
     * @return string Error message.
     */
    function qp_med($query)
    {
        $src = $this->get_filename($query);
        if (!file_exists($query))
            $this->make_preview($src, $query);

        return $this->qp_file_generic($query);
    }

    /**
     * Creates a thumbnail and outputs it to the browser.
     *
     * @param $query string Path to image.
     * @return string Error message.
     */
    function qp_small($query)
    {
        $src = $this->get_filename($query);
        if (!file_exists($query))
            $this->make_thumb($src, $query);

        return $this->qp_file_generic($query);
    }

    /**
     * Outputs a specified file to the browser as an image.
     *
     * @param $file string Path to image.
     * @return string Error message.
     */
    function qp_file_generic($file)
    {
        if (!file_exists($file))
            return '<div class="errormsg">File does not exist!</div>';

        header("Content-Type: image/jpeg");
        readfile($file);
        exit;
    }

    /**
     * The unspeakable has happened.
     *
     * @param $query string Useless parameter to comply with the interface.
     * @return string The manifestation of sheer terror.
     */
    function qp_wtf($query)
    {
        return '<div class="errormsg"><marquee><span style="font-size: 72px;">WUT?!&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#3232;___&#3232;</span></marquee></div>';
    }

    /**
     * Scans through all files in the directory and saves the result to cache.
     *
     * @param $dir string Path to a directory.
     */
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

    /**
     * Scans through all subfolders in the directory and saves the result to cache.
     *
     * @param $dir string Path to a directory.
     */
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
                        if ($curr != '.' && $curr != '..' && is_dir($temp))
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

    /**
     * Retrieves the number of files in a folder, either from cache or directly.
     *
     * @param $dir string Path to directory.
     * @return int Number of files.
     */
    function count_files($dir)
    {
        if (file_exists($dir . '/.files'))
            return count(file($dir . '/.files'));

        $count = 0;

        if ($handle = opendir($dir))
        {
            while (($curr = readdir($handle)) !== false)
            {
                if($curr == '.' || $curr == '..')
                    continue;

                $path = $dir . '/' . $curr;
                if(is_file($path))
                    $count++;
            }
        }

        return $count;
    }

    /**
     * Recursively outputs the directory tree.
     *
     * @param $root string The base folder for current one.
     * @param $data mixed Current tree node.
     */
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

    /**
     * Drops off known postfixes from the filename.
     *
     * @param $query string Query string.
     * @return string File path without postfix.
     */
    private function get_filename($query)
    {
        $postfixes = array('.m', '.s', '.view');
        foreach($postfixes as $pfx)
        {
            $len = strlen($pfx);
            if(substr($query, -$len) === $pfx)
                return substr($query, 0, -$len);
        }

        return $query;
    }

    /**
     * Outputs the general frame of the gallery.
     *
     * @param $result string HTML code of the currently displayed item.
     */
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

    /**
     * Handles all the stuff.
     */
    function process()
    {
        $this->load_dirs('.', $this->all_dirs);
        $this->determine_mode();
        $result = $this->{$this->setup['mode']}($this->setup['query']);
        $this->output($result);
    }
}

$qp = new qp();
$qp->process();