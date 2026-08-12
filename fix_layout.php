<?php
$dir = 'C:/xampp/htdocs/Digi Pro X 24/admin';
$files = glob($dir . '/*.php');

$search = <<<HTML
    <div class="sidebar">
        <a href="../index.php" class="sidebar-brand">🌐 View Site</a>
            <a href="../logout.php" style="text-align: center;">🚪 Logout</a>
        </div>
        <ul class="sidebar-menu">
HTML;

$search2 = <<<HTML
    <div class="sidebar">
        <a href="../index.php" class="sidebar-brand">>🌐 View Site</a>
            <a href="../logout.php" style="text-align: center;">>🚪 Logout</a>
        </div>
        <ul class="sidebar-menu">
HTML;

$replace = <<<HTML
    <div class="sidebar">
        <a href="../index.php" class="sidebar-brand">
            Digi Pro X 24 <br><span>Admin Panel</span>
        </a>
        <div class="sidebar-footer">
            <a href="../index.php" style="text-align: center;">🌐 View Site</a>
            <a href="../logout.php" style="text-align: center;">🚪 Logout</a>
        </div>
        <ul class="sidebar-menu">
HTML;

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Normalize newlines in search/replace for robust matching
    $s1 = str_replace("\r\n", "\n", $search);
    $s2 = str_replace("\r\n", "\n", $search2);
    $r = str_replace("\r\n", "\n", $replace);
    $c = str_replace("\r\n", "\n", $content);
    
    $fixed = str_replace($s1, $r, $c);
    $fixed = str_replace($s2, $r, $fixed);
    
    // Catch cases where the original might have had different spaces
    // Alternatively just use regex
    $fixed = preg_replace(
        '/<div class="sidebar">\s*<a href="\.\.\/index\.php" class="sidebar-brand">>?🌐 View Site<\/a>\s*<a href="\.\.\/logout\.php" style="text-align: center;">>?🚪 Logout<\/a>\s*<\/div>\s*<ul class="sidebar-menu">/u',
        $r,
        $fixed
    );
    
    if ($fixed !== $c) {
        file_put_contents($file, $fixed);
        echo "Fixed layout: " . basename($file) . "\n";
    }
}
?>
