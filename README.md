# mw_to_md

PHP script to convert a wiki from Mediawiki to Github Markdown.

I developed this to convert a particular MW wiki (the BOINC user's manual).
It may require some tweaking to work for you.
If you make a general improvement, please submit a PR.

The script starts with a page in the MW wiki
and recursively follows internal links,
converting the pages it finds.
Thus it skips old unreferenced pages in the MW wiki.

Assumptions:
* You have a working Mediawiki wiki, possibly including image files.
* You have a Github wiki, with images stored in a directory **images/**.
Clone this wiki to, say, **repo.wiki**.

To use:

* Make a directory, say **~/mw_convert**, and create subdirectories **mw**, **md**, **all_images**, and **images**.
* Put the script (**mw_to_md.php**) in this directory.
* Copy the image files from the MW wiki:

     cd ~/mediawiki/images
     cp */*/*.png ~/mw_convert/all_images

* foo
