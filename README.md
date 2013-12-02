Quickpix
========

Quickpix is a lightweight photogallery script written in PHP. It is designed to be simple and transparent, relying on the file system rather than a database.

Features:

* 1 single PHP script + htaccess file
* No database required
* Automatic preview and thumbnail generation
* Download entire folders as a single .zip file
* Configurable descriptions
* Hidden folders

### Requirements

* PHP 5.2+ with GD extension
* Apache with mod_rewrite
* Write access to directory folder
 
### Installation

* Copy the `index.php` and `.htaccess` files to the root of your web gallery.
* Make sure the folder has write access! Quickpix caches a lof of stuff.
* Update the `.htaccess` file with your gallery's relative path.
* Update the `index.php` file with your gallery's relative path as well, or set any other options (thoroughly commented).
* Create folders and upload pictures. That's it!
 
### How to work with it

Quickpix uses the file system as a single source of truth. You can think of it as `Options +Indexes` page on steroids that automatically creates thumbnails and previews for you.

When Quickpix comes across an unknown folder, it creates a `.info` file in it. It is a JSON file which you can edit in any text editor and specify titles and descriptions for some folders. Therefore, most of the manipulations are performed via the file system: adding or removing folders, files, and editing the `.info` files. If your local PC is a webserver, its even simplier!

If a folder does not update automatically, you can force the update by appending the "?update" to the folder path:

    http://example.com/photos/your-folder/?update
  
To remove all cached thumbnails and the zipped folder, append "?clean":

    http://example.com/photos/your-folder/?clean
    
### Hidden folders

By default, all existing subfolders are listed in the tree at the left side. If you wish to exclude a folder from the tree, add a `.hidden` file to it. The folder itself and its contents will not appear in the tree.

Please note that hidden folders are *available by direct links*. They are just not referenced anywhere in the gallery, but you are free to give direct links to whoever you deem worthy!

### Zip arhives

You can let viewers download entire folders as a single zip file. The `ALLOW_ZIP` setting in the `index.php` file must be set to true (and so it is by default).

Please note that zip files are cached and can take a considerable amount of disk space!
