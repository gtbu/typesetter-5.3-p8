Features

* php 8.1 - 8.4
* Jquery UI 14.1 and jquery 3.6.3
* Ckeditor 4.22 with skin-switch
* Scssphp 1.12 compiler with Bootstrap 5.3 support
* Less 3.13.1 compiler
* Elfinder 2.1.62
* PhpMailer 6.9.1 and Google Recaptcha
* Local fonts under themes/Bootstrap4/assets
* Encrypted login

<p align="center"><img src="/include/imgs/typesetter/ts-logo-color-100x100px-min.png?raw=true"/></p>
<h1 align="center">Typesetter CMS </h1>
<p align="center">Open source CMS written in PHP focused on ease of use with true WYSIWYG editing and flat-file storage.<br/><br/></p>


* [Typesetter Home](https://www.typesettercms.com) - now closed
* [Typesetter 5.3 Wiki](https://github.com/gtbu/typesetter-5.3-p8/wiki) with more detailed instructions !


## Requirements ##
* PHP 8.0 - 8.4


## Installation ##
1. Download this release of Typesetter

2. Upload the extracted contents to your server (with filezilla-portable) and put a domain on the directory

3. Using your web browser, navigate to the folder you just uploaded the unzipped contents to 

4. Complete the installation form and submit

## Contribute ##
Submitting bug fixes and enhancements is easy:

1. Log in to GitHub

2. Fork the Typesetter Repository
  * https://github.com/gtbu/typesetter-5.3-p8
  
  * Click "Fork" and you'll have your very own copy of the Typesetter source code at https://github.com/{your-username}/Typesetter

3. Edit files within your fork.
  
4. Submit a Pull Request (tell Typesetter about your changes)
  * Click "Pull Request"
  * Enter a Message that will go with your commit to be reviewed by core committers
  * Click “Send Pull Request”

### Multiple Pull Requests and Edits ###
When submitting pull requests, it is extremely helpful to isolate the changes you want included from other unrelated changes you may have made to your fork of Typesetter. The easiest way to accomplish this is to use a different branch for each pull request. There are a number of ways to create branches within your fork, but GitHub makes the process very easy:

1. Start by finding the file you want to edit in Typesetter's code repository at
 https://github.com/gtbu/
2. Once you have located the file, navigate to the code view and click "Edit". For example, if you want to change the /include/common.php file, the "Edit" button would appear on this page: https://github.com/Typesetter/Typesetter/blob/master/include/common.php
3. Now, edit the file as you like then click "Propose File Change"

## Plugins and php8 ##
  * Many plugins <a href="https://www.typesettercms.com/Plugins" target=_blank> from the forum</a> will not function with Php 8, because deprecations of Php 7 are errors in php8. 
  The momentary solution is to open an issue (since Typesettercms version 5.3.2 the Symfony polyfills reduce this problem).

## Problems with updates ##
If You have questions regarding installation please look here at the top in the WIKI.

The update of scssphp to version 1.11 is important for bootstrap 5. 
Old bootstrap 3 - based themes had a small bug in variables.css which has been corrected.
