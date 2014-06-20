<?php
/**
 * Logwatch-RSS
 *
 * @author Anael Ollier (nanawel.at.nospam.gmail.dot.com)
 * @since 2013-07-18
 * 
 * Example of cron job for generating source report files:
 * 20 2 * * * root /usr/sbin/logwatch | tee /var/logwatch-archives/logwatch_$(date +%Y-%m-%d).txt
 */

define('LOGWATCH_RSS_SOURCE_DIR', '/var/logwatch-archives');    // Adapt to your liking

function _prepareFiles($files) {
	$return = array();
	if (is_array($files)) {
		foreach ($files as $file) {
			$key = filemtime($file);
			$return[$key] = $file;
		}
	}
	return $return;
}
function _getFormatedFileContent($file) {
	return str_replace(array("\n", "\r\n"), '<br />', htmlentities(file_get_contents($file)));
}
function _getFileOwnerName($file) {
	$info = posix_getpwuid(fileowner($file));
	return $info['name'];
}
function getSortedFiles() {
    $files = _prepareFiles(glob(LOGWATCH_RSS_SOURCE_DIR . '/*'));
    krsort($files);
    return $files;
}

function atomFeed() {
    $_myUri = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    $_hostname = gethostname();
    $_files = getSortedFiles();

    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Type: application/atom+xml; charset=UTF-8');
    ?>
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><?php echo $_hostname ?> Logwatch RSS</title>
	<subtitle>Logwatch archives for host <?php echo $_hostname ?></subtitle>
	<updated><?php echo date(DateTime::ATOM, time()) ?></updated>
	<link href="http://<?php echo $_hostname ?>/" />
	<link href="<?php echo $_myUri ?>" rel="self" />
	<id><?php echo 'logwatch-rss:' . md5(LOGWATCH_RSS_SOURCE_DIR) . ':' . $_hostname ?></id>

	<?php foreach ($_files as $_filemtime => $_file): ?>
		<?php $_fileOwnerName = _getFileOwnerName($_file) ?>
		<entry>
			<id><?php echo $_myUri . md5($_file . $_filemtime) ?></id>
			<link href="<?php echo $_myUri . '?f=' . $_filemtime ?>"/>
			<title><?php echo basename($_file) ?></title>
			<author>
				<name><?php echo $_fileOwnerName ?></name>
				<email><?php echo $_fileOwnerName . '@' . $_SERVER['SERVER_NAME'] ?></email>
			</author>
			<updated><?php echo date(DateTime::ATOM, $_filemtime) ?></updated>
			<content type="html">
				<![CDATA[<h1><?php echo basename($_file), ' ', date('(Y-m-d H:i:s)', $_filemtime)?></h1><div style="font-family: monospace;"><pre><?php echo _getFormatedFileContent($_file) ?></pre></div>]]>
			</content>
		</entry>
	<?php endforeach; ?>
</feed>
<?php
}

function getFeedContent($filemtime) {
    $_files = getSortedFiles();
    if (isset($_files[$filemtime])) {
        $_file = $_files[$filemtime];
        header('Content-Type: text/html; charset=UTF-8');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600 * 24 * 30)); // 30 days
        ?>
        <html>
            <body>
                <h1><?php echo basename($_file), ' ', date('(Y-m-d H:i:s)', $filemtime)?></h1>
                <div style="font-family: monospace;">
                    <pre><?php echo _getFormatedFileContent($_file) ?></pre>
                </div>
            </body>
        </html>
        <?php
    }
    die('Not found');
}

if (isset($_GET['f']) && (int) $_GET['f']) {
    getFeedContent((int) $_GET['f']);
}
else {
    atomFeed();
}