<?php
// plugin_hilfe.php – eingebettete Hilfedatei für ein Plugin

//defined('is_running') or die('Not an entry point...');

header('Content-Type: text/html; charset=UTF-8');
$pageTitle = "Installation support";

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Hilfe zur Plugin-Installation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            max-width: 99vw;
            margin: 2rem auto;
            padding: 0 1rem;
            color: #222;
        }
        h1 {
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
        }
        h2 {
            font-size: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }
        p {
            margin: 0.5rem 0 1rem;
        }
    </style>
</head>
<body>
<br>
    <h3>Plugin Installation Help</h3>
<br>
<h4>Basic Installation</h4>
<p>
This plugin adds a commenting feature to your website's content. 
</p>
<p>
Installation alone is not sufficient. You must manually insert PHP code
(embedded within PHP tags <code>&lt;?php ... ?&gt;</code>) into the <code>template.php</code> file of the relevant theme:
<pre>
if (class_exists('gpOutput') && method_exists('gpOutput', 'GetGadget')) {
gpOutput::GetGadget('Easy Comments');}
</pre>
</p>
<h4>FAQs and Tips</h4>
<p>
If the plugin does not work as expected, first check that all
requirements are met (at least PHP 8.0, current CMS version). 
</p>
<p>
For further assistance, consult the plugin documentation or the <a href="https://github.com/gtbu/Typesetter5.2/wiki/Gadgets">Wiki</a>. 
<br>
Regular updates ensure the plugin remains secure and compatible. 
</p>
</body>
</html>