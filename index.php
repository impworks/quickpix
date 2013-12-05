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
 * Checks if all generated cache files (thumbnails, archives, etc.) can be removed by opening /foldername/.clean.
 * Default = true.
 * Can be disabled in case of malicious requests.
 */
define('ALLOW_CLEAN', true);

/**
 * Checks if users are allowed to download entire directories as zip archives.
 * Zip archive is cached, therefore it requires additional space.
 * Default = true.
 */
define('ALLOW_ZIP', true);

class qp
{
    private $all_dirs;

    // ================================================================================
    //                            Startup & dispatching
    // ================================================================================

    /**
     * Handles all the stuff.
     */
    function process()
    {
        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);

        $setup = $this->determine_mode();
        $result = $this->{'qp_' . $setup['mode']}($setup['dir'], $setup['file']);
        $this->output($result);
    }

    /**
     * Dispatcher method that detects mode depending on query.
     *
     * @return array Array with route data.
     */
    function determine_mode()
    {
        $q = $_SERVER['REQUEST_URI'];
        $data = array();

        if (preg_match('/^\/(?<dir>.*?)\/(?<file>[^\/]+\.[sm])$/i', $q, $data))
            $mode = 'preview';

        elseif (preg_match('/^\/(?<dir>.*?)\/(?<file>[^\/]+)\.view$/i', $q, $data))
            $mode = 'view';

        elseif (preg_match('/^\/(?<dir>.*?)\?update$/i', $q, $data))
            $mode = 'update';

        elseif (preg_match('/^\/(?<dir>.*?)\?clean/i', $q, $data))
            $mode = 'clean';

        elseif (preg_match('/^\/(?<dir>.*?)\/(?<file>[^\/]+)\.zip$/i', $q, $data))
            $mode = 'zip';

        elseif (preg_match('/^\/(?<dir>.*?)\/?$/i', $q, $data))
            $mode = 'dir';

        else
            $mode = 'wtf';

        if ($data['dir'])
            $data['dir'] = preg_replace('/(\/*$|\.\.\/)/i', '', $data['dir']);
        else
            $data['dir'] = '.';

        return array('mode' => $mode, 'dir' => $data['dir'], 'file' => $data['file']);
    }

    // ================================================================================
    //                                    Commands
    // ================================================================================

    /**
     * Displays directory content.
     *
     * @param $dir string Directory path.
     * @param $file string File name with extension, if applicable.
     * @return string HTML code to output.
     */
    function qp_dir($dir, $file)
    {
        if (!is_dir($dir))
            return '<div class="error-msg">Directory does not exist!</div>';

        $info = $this->scan_dir($dir);
        $return = '';

        if ($info['dirs'])
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

            $items = count($info['dirs']);
            $rows = ceil($items / ITEMS_IN_ROW);
            $space = 100 / ITEMS_IN_ROW;
            $curr = 0;

            $dirnames = array_keys($info['dirs']);

            for ($idx = 0; $idx < $rows; $idx++)
            {
                $return .= '<tr>';

                for ($idx2 = 0; $idx2 < ITEMS_IN_ROW; $idx2++)
                {
                    $dirname = $dirnames[$curr];
                    $subdir = $info['dirs'][$dirname];
                    $return .= '<td class="cell text" width="' . $space . '%" align="center" valign="middle">';

                    if ($curr < $items)
                    {
                        $return .= '<div class="folder thumb">';

                        foreach($this->get_folder_thumbnails(util::combine($dir, $dirname)) as $thumb)
                            $return .= '<a href="' . $dirname . '/"><img src="' . util::combine($dirname, $thumb) . '.s" class="folder-thumb" /></a>';

                        $return .= '</div><a href="' . $dirname . '/">' . util::coalesce(trim($subdir['caption']), $dirname) . '</a>';
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

        if ($info['files'])
        {
            $dirname = util::pathinfo($dir, 'basename');

            $return .= '
          <div class="block">
            <table cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td class="header">' . $this->breadcrumb($dir) . '</td>
                <td class="header" align="right" width="20%">' . ( ALLOW_ZIP ? '<a href="' . $dirname . '.zip">Download as archive</a>' : '' ) . '</td>
              </tr>
              <tr>
                <td class="text cell" colspan="2">
                  <table cellpadding="12" cellspacing="0" border="0" width="100%">';

            $items = count($info['files']);
            $rows = ceil($items / ITEMS_IN_ROW);
            $curr = 0;
            $filenames = array_keys($info['files']);
            for ($idx = 0; $idx < $rows; $idx++)
            {
                $return .= '<tr>';

                for ($idx2 = 0; $idx2 < ITEMS_IN_ROW; $idx2++)
                {
                    $return .= '<td class="text" width="25%" align="center" valign="middle">';
                    if ($curr < $items)
                    {
                        $filename = $filenames[$curr];
                        $file = $info['files'][$filename];
                        $return .= '<a href="' . $filename . '.view"><img src="' . $filename . '.s" border="0" class="thumb"><br />' . util::coalesce(trim($file['caption']), $filename) . '</a>';
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

        if (!$info['dirs'] && !$info['files'])
            return '<div class="error-msg">Directory is empty!</div>';

        return $return;
    }

    /**
     * Displays single file preview.
     *
     * @param $dir string Directory path.
     * @param $file string File name with extension, if applicable.
     * @return string HTML code to output.
     */
    function qp_view($dir, $file)
    {
        $filename = $this->get_filename($file);
        $path = util::combine($dir, $filename);

        if (!file_exists($path))
            return '<div class="error-msg">File does not exist!</div>';

        $info = $this->scan_dir($dir);
        $filenames = array_keys($info['files']);
        $idx = array_search($filename, $filenames);
        $prev = $idx > 0 ? $filenames[$idx - 1] : false;
        $next = $idx < count($filenames) - 1 ? $filenames[$idx + 1] : false;

        return '
  <div class="block">
    <table cellpadding="0" cellspacing="0" border="0" width="100%">
      <tr>
        <td class="header">' . $this->breadcrumb($dir, util::coalesce($info['files'][$filename]['caption'], $file)) . '</td>
      </tr>
      <tr>
        <td class="text cell" align="center" valign="center"><br />
          <a href="' . $file . '"><img src="' . $file . '.m" border="0" title="View full picture"></a><br /><br />
          <table cellpadding="10" cellspacing="0" border="1" class="pic-info" bordercolor="#999999">
            <tr>
              <td class="text" width="120" align="center">' . ($prev ? '<nobr>&laquo; <a title="Previous picture" id="link_prev" href="' . $prev . '.view">' . util::coalesce(trim($info['files'][$prev]['caption']), $prev) . '</a></nobr>' : '&nbsp;') . '</td>
              <td class="text" width="400" align="center">' . $info['files'][$filename]['descr'] . '</td>
              <td class="text" width="120" align="center">' . ($next ? '<nobr><a title="Next picture" id="link_next" href="' . $next . '.view">' . util::coalesce(trim($info['files'][$next]['caption']), $next) . '</a> &raquo;</nobr>' : '&nbsp;') . '</td>
            </tr>
          <table>
        </td>
      </tr>
    </table>
  </div>';
    }

    /**
     * Creates a preview and outputs it to the browser.
     *
     * @param $dir string Directory path.
     * @param $file string File name with extension, if applicable.
     * @return string Error message.
     */
    function qp_preview($dir, $file)
    {
        $dest = util::combine($dir, $file);
        $src = $this->get_filename($dest);
        if (!file_exists($dest))
        {
            if(util::has_extension($file, 'm'))
                $this->make_preview($src, $dest);
            else
                $this->make_thumb($src, $dest);
        }

        return $this->file_generic($dest);
    }

    /**
     * Creates a zip archive with all files in the current directory.
     * Does not include subdirectories.
     *
     * @param $dir string Directory path.
     * @param $file string File name with extension, if applicable.
     * @return mixed Error message or zip archive to download.
     */
    function qp_zip($dir, $file)
    {
        if(!ALLOW_ZIP)
        {
            header("Location: /" . $dir);
            exit;
        }

        if (!is_dir($dir))
            return '<div class="error-msg">Directory not found!</div>';

        $filename = util::pathinfo($dir . '/', 'basename') . '.zip';
        $info = $this->scan_dir($dir);

        $zip = new ZipArchive;
        $zip->open(util::combine($dir, $filename), ZipArchive::CREATE);

        foreach ($info['files'] as $name => $_)
            $zip->addFile(util::combine($dir, $name), $name);

        $zip->close();

        header('Location: ' . $filename);
    }

    /**
     * Updates the subfolder & file cache for a directory in case its content has been modified.
     *
     * @param $dir string Directory path.
     * @param $file string File name with extension, if applicable.
     */
    function qp_update($dir, $file)
    {
        if(!ALLOW_UPDATE || !is_dir($dir))
        {
            header("Location: .");
            exit;
        }

        $cache = util::combine($dir, '.info');
        $info = file_exists($cache)
            ? json_decode(file_get_contents($cache), true)
            : array('dirs' => array(), 'files' => array());

        $contents = scandir($dir);
        foreach($contents as $curr)
        {
            if($curr == '..' || $curr == '.')
                continue;

            $path = util::combine($dir, $curr);
            if(is_dir($path))
            {
                if(is_array($info['dirs'][$curr]))
                    $info['dirs'][$curr]['checked'] = true;
                else
                    $info['dirs'][$curr] = array('caption' => '', 'files' => $this->count_files($path), 'checked' => true);
            }
            elseif(is_file($path) && util::has_extension($path, $this->image_extensions()))
            {
                if(is_array($info['files'][$curr]))
                    $info['files'][$curr]['checked'] = true;
                else
                    $info['files'][$curr] = array('caption' => '', 'descr' => '', 'checked' => true);
            }
        }

        // remove unneeded entries
        foreach($info as $kind => $list)
        {
            foreach($list as $record => $values)
            {
                if($values['checked'])
                    unset($info[$kind][$record]['checked']);
                else
                    unset($info[$kind][$record]);
            }
        }

        file_put_contents($cache, json_encode($info, JSON_PRETTY_PRINT));
        header("Location: .");
    }

    /**
     * Removes all cached files in the directory.
     *
     * @param $dir string Directory path.
     * @param $file string File name with extension, if applicable.
     */
    function qp_clean($dir, $file)
    {
        if(!ALLOW_CLEAN)
        {
            header("Location: .");
            exit;
        }

        $exts = array( "s", "m", "zip" );

        if(is_dir($dir))
        {
            $files = scandir($dir);
            foreach($files as $file)
            {
                $path = util::combine($dir, $file);
                if(util::has_extension($file, $exts) && is_file($path))
                    unlink($path);
            }
        }

        header("Location: .");
    }

    /**
     * The unspeakable has happened.
     *
     * @return string The manifestation of sheer terror.
     */
    function qp_wtf()
    {
        return '<div class="error-msg"><marquee><span style="font-size: 72px;">WUT?!&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#3232;___&#3232;</span></marquee></div>';
    }

    // ================================================================================
    //                                 Helper methods
    // ================================================================================

    /**
     * Returns a cached list of all directories available to the gallery.
     *
     * @return mixed The list of all directories.
     */
    function get_all_dirs()
    {
        if(!$this->all_dirs)
            $this->all_dirs = $this->load_dirs('.');

        return $this->all_dirs;
    }

    /**
     * Loads data from a pre-cached .dirs file.
     *
     * @param $from string Path to directory.
     * @return mixed The array of directories, or false if there is none.
     */
    function load_dirs($from)
    {
        $cache = $this->get_cache($from);
        if(!$cache)
            return false;

        $result = array();

        foreach ($cache['dirs'] as $key => $val)
        {
            $result[$key] = $val;
            $result[$key]['subs'] = $this->load_dirs(util::combine($from, $key));
        }

        return $result;
    }

    /**
     * Generates breadcrumb HTML code string for current folder and/or path.
     *
     * @param $dir string Directory path.
     * @param $file mixed File name, if any.
     * @return string HTML code to output.
     */
    function breadcrumb($dir, $file = false)
    {
        $pieces = explode("/", $dir);
        $count = count($pieces);
        $folder = $this->get_all_dirs();
        $result = '<a href="http://' . $_SERVER['HTTP_HOST'] . ROOT_DIR . '">' . TITLE . '</a>';
        $path = '';

        for ($idx = 0; $idx < $count; $idx++)
        {
            if (!$folder)
                continue;

            foreach ($folder as $key => $val)
            {
                if (!strcasecmp($key, $pieces[$idx]))
                {
                    $path .= '/' . $key;

                    $crumb = util::coalesce(trim($val['caption']), $key);
                    if($idx < $count - 1 || $file)
                        $crumb = '<a href="http://' . $_SERVER['HTTP_HOST'] . util::combine(ROOT_DIR, $path) . '/">' . $crumb . '</a>';

                    $result .= ' &raquo; ' . $crumb;
                    $folder = $val['subs'];
                    break;
                }
            }
        }

        if($file)
            $result .= ' &raquo; ' . $file;

        return $result;
    }

    /**
     * Outputs a specified file to the browser as an image.
     *
     * @param $file string Path to image.
     * @return string Error message.
     */
    function file_generic($file)
    {
        if (!file_exists($file))
            return '<div class="error-msg">File does not exist!</div>';

        header("Content-Type: image/jpeg");
        readfile($file);
        exit;
    }

    /**
     * Scans through all files in the directory and saves the result to cache.
     *
     * @param $dir string Path to a directory.
     * @return array List of files and subdirectories in current directory.
     */
    function scan_dir($dir)
    {
        $cache = $this->get_cache($dir);
        if($cache)
            return $cache;

        $info = array('files' => array(), 'dirs' => array());

        $contents = scandir($dir);
        foreach($contents as $curr)
        {
            if($curr == '.' || $curr == '..')
                continue;

            $path = util::combine($dir, $curr);

            if(is_dir($path))
                $info['dirs'][$curr] = array('caption' => '', 'files' => $this->count_files($path));

            elseif(is_file($path) && util::has_extension($path, $this->image_extensions()))
                $info['files'][$curr] = array('caption' => '', 'descr' => '');
        }

        file_put_contents(util::combine($dir, '.info'), json_encode($info, JSON_PRETTY_PRINT));
        return $info;
    }

    /**
     * Retrieves the number of files in a folder, either from cache or directly.
     *
     * @param $dir string Path to directory.
     * @return int Number of files.
     */
    function count_files($dir)
    {
        $cache = $this->get_cache($dir);
        if($cache)
            return count($cache['files']);

        $count = 0;

        foreach(scandir($dir) as $curr)
        {
            if($curr == '.' || $curr == '..')
                continue;

            $path = util::combine($dir, $curr);
            if(is_file($path) && util::has_extension($path, $this->image_extensions()))
                $count++;
        }

        return $count;
    }

    /**
     * Recursively outputs the directory tree.
     *
     * @param $root string The base folder for current one.
     * @param $data mixed Current tree node.
     * @param $depth int Depth index
     */
    function output_tree($root, $data, $depth = 0)
    {
        if (!is_array($data) || !count($data))
        {
            if($depth == 0)
                echo '<div class="error-msg">Directory is empty!</div>';

            return;
        }

        foreach ($data as $key => $val)
        {
            echo '<div class="pck-header ' . ($depth == 0 ? 'pck-branch' : 'pck-subbranch') . '"><div class="pck-menu">' . ($val['files'] > 0 ? $val['files'] : '') . '</div><a href="http://' . $_SERVER['HTTP_HOST'] . '/' . $root . $key . '/' . '">' . util::coalesce(trim($val['caption']), $key) . '</a></div>';
            if (!is_array($val['subs']) || !count($val['subs']))
                continue;

            echo '<div class="pck-sub">';
            $this->output_tree($root . $key . '/', $val['subs'], $depth+1);
            echo '</div>';
        }
    }

    /**
     * Drops off known postfixes from the filename.
     *
     * @param $query string Query string.
     * @return string File path without any known postfix.
     */
    private function get_filename($query)
    {
        return preg_replace('/\.(m|s|view)$/i', '', trim($query));
    }

    /**
     * Loads image thumbnails for a folder.
     *
     * @param $dir
     * @return array Up to 4 image thumbnails.
     */
    private function get_folder_thumbnails($dir)
    {
        $max = 4;
        $idx = 0;

        $cache = $this->get_cache($dir);
        $imgs = array();

        if($cache)
        {
            foreach($cache['files'] as $key => $info)
            {
                $imgs[] = $key;
                if(++$idx >= $max)
                    break;
            }
        }
        else
        {
            if($handle = opendir($dir))
            {
                while(($curr = readdir($handle)) !== false)
                {
                    $path = util::combine($dir, $curr);
                    if($curr == '.' || $curr == '..' || is_dir($path) || !util::has_extension($curr, $this->image_extensions()))
                        continue;

                    $imgs[] = $curr;
                    if(++$idx >= $max)
                        break;
                }
            }
        }

        return $imgs;
    }

    /**
     * Attempts to load cache from a directory.
     *
     * @param $dir string Directory path
     * @return mixed|null Cache
     */
    private function get_cache($dir)
    {
        $path = util::combine($dir, '.info');
        return file_exists($path) ? json_decode(file_get_contents($path), true) : null;
    }

    // ================================================================================
    //                              Image manipulations
    // ================================================================================

    /**
     * Creates a preview image and stores it on the disk.
     * The preview image is proportionally resized to fit into a 640x640 px square.
     *
     * @param $src string Path to source image.
     * @param $dest string Path to destination image.
     * @return bool Success flag.
     */
    function make_preview($src, $dest)
    {
        $size = 640;

        list($x, $y) = getimagesize($src);
        $imgsrc = $this->load_image($src);

        if(!$imgsrc)
            return false;

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

        return false;
    }

    /**
     * Creates a thumbnail image and stores it on the disk.
     * The thumbnail image is a 120x120 square cropped in the center of the original image.
     *
     * @param $src string Path to source image.
     * @param $dest string Path to destination image.
     * @return bool Success flag.
     */
    function make_thumb($src, $dest)
    {
        $size = 120;

        list($x, $y) = getimagesize($src);
        $imgsrc = $this->load_image($src);

        if(!$imgsrc)
            return false;

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

        return true;
    }

    /**
     * Creates an image from the path.
     *
     * @param $path string Image path.
     * @return null|resource Image
     */
    function load_image($path)
    {
        if(util::has_extension($path, array("jpeg", "jpg")))
            return imagecreatefromjpeg($path);

        if(util::has_extension($path, "png"))
            return imagecreatefrompng($path);

        if(util::has_extension($path, "gif"))
            return imagecreatefromgif($path);

        return null;
    }

    /**
     * Returns the list of known image extensions.
     *
     * @return array
     */
    public static function image_extensions()
    {
        return array("jpg", "jpeg", "png", "gif");
    }

    // ================================================================================
    //                                 Main template
    // ================================================================================

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
      .block
      {
        border: 1px #666688 solid;
        padding: 0px;
        width: 100%;
        display: block;
        position: relative;
      }

      .block:not(:first-child)
      {
        margin-top: 16px;
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

      .text-small
      {
        font-family: Verdana;
        font-size: 9px;
        color: #000000;
        background-color: #FFFFFF;
      }

      .error-msg
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
      }

      .pck-header:not(:first-child)
      {
        margin-top: 4px;
      }

      .pck-header a
      {
        text-decoration: none;
        color: #000000;
        display: block;
        padding: 4px;
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
        padding: 4px 0 0 16px;
        border: 0;
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
      
      .pic-info
      {
        border-collapse: collapse;
        background-color: #ECE9D8;
        margin: 16px;
      }

      .folder
      {
        border: 2px #CCCCCC solid;
        padding: 0 4px 4px 0;
      }

      .folder a
      {
        margin: 4px 0 0 4px;
        display: inline-block;
      }

      .folder-thumb
      {
        width: 56px;
        height: 56px;
      }

      .thumb
      {
        margin-bottom: 4px;
        width: 120px;
        height: 120px;
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
                    <td class="header"><a href="' . ROOT_DIR . '">' . TITLE . '</a></td>
                  </tr>
                  <tr>
                    <td class="text cell">';

        // output trees
        $this->output_tree('', $this->get_all_dirs());

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
}

/**
 * The collection of various tools.
 */
class util
{
    /**
     * Combines the parts of paths, removing extra slashes.
     *
     * @return string Path combined.
     */
    public static function combine()
    {
        $args = func_get_args();
        foreach($args as $id => $arg)
            $args[$id] = trim($arg, " \t\n\r\0\x0B/\\");

        return implode('/', $args);
    }

    /**
     * Checks if a file name ends with any of the specified extensions.
     * @param $file string File name or path.
     * @param $extensions mixed Array of extensions or a single extension.
     * @return bool
     */
    public static function has_extension($file, $extensions)
    {
        if(!is_array($extensions))
            $extensions = array($extensions);

        $info = pathinfo($file);
        foreach($extensions as $ext)
            if(strcasecmp($ext, $info['extension']) === 0)
                return true;

        return false;
    }

    /**
     * Gets the information piece about a file.
     *
     * @param $file string String path.
     * @param $item string Mode: 'dirname', 'basename', 'extension' or 'filename'.
     * @return string File name without extension.
     */
    public static function pathinfo($file, $item)
    {
        $info = pathinfo($file);
        return $info[$item];
    }

    /**
     * Checks if a string ends with another string.
     * @param $str string Haystack.
     * @param $substr string Needle.
     * @param $case bool Checks if the comparison must be case-sensitive.
     * @return bool
     */
    public static function ends_with($str, $substr, $case = true)
    {
        if(!$case)
        {
            $str = strtolower($str);
            $substr = strtolower($substr);
        }

        $strlen = strlen($str);
        $sublen = strlen($substr);
        return $strlen >= $sublen && substr($str, -$sublen) == $substr;
    }

    /**
     * Returns the first non-null item from given list.
     *
     * @return mixed Item.
     */
    public static function coalesce()
    {
        $args = func_get_args();
        foreach($args as $arg)
            if($arg)
                return $arg;

        return null;
    }
}

$qp = new qp();
$qp->process();