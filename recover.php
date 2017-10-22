<?php

const REGEX = '/^([\+]+) [dr]{1}\/[dr]{1} (\d+-\d+-\d+)\:[ \t]+(.*)$/';
const REGEX_DIRECTORY = '/^[\+]+ d\/d (\d+-\d+-\d+)\:[ \t]+(.*)$/';
const REGEX_FILE = '/^[\+]+ r\/r (\d+-\d+-\d+)\:[ \t]+(.*)$/';
const recoverLog = '/raid/recovery/recover.log';

$sourceImage = '/raid/recovery/disk.img';
$sourceFile = 'file-list.txt';
$rootNode = '266382-144-6:';
$outputDirectory = 'DataHenk';

$handle = fopen($sourceFile, "r");
if (!$handle) {
    exit (sprintf('Could not open [%s]%s', $sourceFile, PHP_EOL));
}
file_put_contents(recoverLog, sprintf('Started recovering: %s', date('Y-m-d H:i:s')));
logMessage(sprintf('Searching for [%s]%s', $rootNode, PHP_EOL));
logMessage(sprintf('Output will be written to [%s]%s', $outputDirectory, PHP_EOL));

$nodeIndenting = 0;
$dirCount = 0;
$fileCount = 0;
$found = false;
$currentIndent = 0;
$i = 0;
$foundIndenting = 0;
$level = 0;
$mode = null;

while (($line = fgets($handle)) !== false) {
    if (!$found) {
        $found = strstr($line, $rootNode) !== false;
        if ($found) {
            logMessage(sprintf('Found root node [%s]%s', $rootNode, PHP_EOL));
            changeDirectory($outputDirectory);
            $foundIndenting = getIndenting($line);
            $currentIndent = $nodeIndenting = $foundIndenting;
            $level = $nodeIndenting;
            $mode = 'dir';
        }
    } else {

        $newIndenting = getIndenting($line);

        if ($newIndenting === null) {
            echo sprintf('Skipping line: [%s]%s', trim($line), PHP_EOL);
            continue;
        } elseif ($newIndenting <= $nodeIndenting) {
            logMessage(sprintf('End of node reached, next node: [%s]%s', getInode($line), PHP_EOL));
            logMessage(sprintf('new indent: [%s], node indent: [%s]%s', $newIndenting, $nodeIndenting, PHP_EOL));
            $found = false;
            break;
        }
        echo sprintf('New Indent: [%d], level: [%d]%s', $newIndenting, $level, PHP_EOL);

        if (strstr($line, 'd/d') && ($newDirectory = getDirectory($line)) !== null) {
            logMessage(sprintf('Creating directory [%s]%s', $newDirectory, PHP_EOL));

            if ($mode === 'dir') {
                if ($newIndenting <= $currentIndent) {
                    changeDirectory('../');
                    $level--;
                }
                if ($newIndenting < $currentIndent) {
                    $multiplier = ($currentIndent - $newIndenting);

                    changeDirectory(str_repeat('../', $multiplier));
                    $level = $level - $multiplier;
                }
            } elseif ($mode === 'file') {
                if ($newIndenting < $currentIndent) {
                    $multiplier = ($currentIndent - $newIndenting);

                    changeDirectory(str_repeat('../', $multiplier));
                    $level = $level - ($multiplier + 1);
                }
            }

            if (!mkdir($newDirectory)) {
                exit(sprintf('Could not create directory: %s/%s%s', getcwd(), $newDirectory, PHP_EOL));
            }
            changeDirectory($newDirectory);
            $level++;
            $dirCount++;
            $mode = 'dir';
        }

        if (strstr($line, 'r/r') && ($newFileName = getFileName($line)) !== null) {
            if ($mode === 'file') {
                if ($newIndenting > $currentIndent) {
                    exit (sprintf('Cannot increase indent file => file %s', PHP_EOL));
                }
                if ($newIndenting < $currentIndent) {
                    $multiplier = $currentIndent - $newIndenting;
                    changeDirectory(str_repeat('../', $multiplier));
                    $level = $level - $multiplier;
                }
            } elseif ($mode === 'dir') {
                if ($newIndenting > $currentIndent) {
                    $multiplier = $currentIndent - $newIndenting;
                    if ($multiplier > 1) {
                        exit (sprintf('Cannot increase indent dir => file by more than one, [%d] given %s', $multiplier, PHP_EOL));
                    }
                    $level++;
                }
                if ($newIndenting == $currentIndent) {
                    changeDirectory('../');
                    $level--;
                }
                if ($newIndenting < $currentIndent) {
                    $multiplier = ($currentIndent - $newIndenting) + 1;
                    changeDirectory(str_repeat('../', $multiplier));
                    $level = $level - $multiplier;
                }
            }

            $cmd = sprintf('icat -r -f ntfs -i raw %s %s > "%s"', $sourceImage, getInode($line), $newFileName);
            logMessage(sprintf('Recovering file [%s/%s]%s', getcwd(), $newFileName, PHP_EOL));
            exec($cmd);
            $fileCount++;
            $mode = 'file';
        }

        $currentIndent = $newIndenting;

        if ($level < $nodeIndenting) {
            echo getcwd() . PHP_EOL;
            exit(sprintf('going south... level [%d] vs indent [%d]%s', $level, $nodeIndenting, PHP_EOL));
        }
    }
}

if ($fileCount || $dirCount) {
    logMessage(sprintf('Recovered [%d] files in [%d] directories', $fileCount, $dirCount));
} else {
    exit ('No files or directories found...' . PHP_EOL);
}

/**
 * @param string $outputDirectory
 */
function changeDirectory($outputDirectory)
{
    $current = getcwd();
    if (!chdir('./' . $outputDirectory)) {
        echo sprintf('Could not change directory [%s]: %s%s', $outputDirectory, implode("\n", error_get_last()),
            PHP_EOL);
        exit;
    }
    $changed = getcwd();
    logMessage(sprintf('Changin directories:%s    %s%s    %s%s', PHP_EOL, $current, PHP_EOL, $changed, PHP_EOL));
    if (getcwd() === '/') {
        exit('whoooops, we are not supposed to be here...');
    }
}

/**
 * @param string $line
 *
 * @return int|null
 */
function getIndenting($line)
{
    $r = preg_match(REGEX, $line, $matches);

    if ($r && count($matches) >= 3) {
        $indenting = strlen($matches[1]);

        return $indenting;
    }

    return null;
}

/**
 * @param string $line
 *
 * @return string|null
 */
function getInode($line)
{
    $r = preg_match(REGEX, $line, $matches);

    if ($r && count($matches) >= 3) {
        return $matches[2];
    }

    return null;
}

/**
 * @param string $line
 *
 * @return string|null
 */
function getDirectory($line)
{
    $r = preg_match(REGEX_DIRECTORY, $line, $matches);

    if ($r && count($matches) >= 3) {
        return $matches[2];
    }

    return null;
}

/**
 * @param string $line
 *
 * @return string|null
 */
function getFileName($line)
{
    $r = preg_match(REGEX_FILE, $line, $matches);

    if ($r && count($matches) >= 3) {
        return $matches[2];
    }

    return null;
}

function logMessage($string)
{
    file_put_contents(recoverLog, $string, FILE_APPEND);
}