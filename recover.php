<?php

$shortopts = "i:s:r::";  // Required value
$longopts = ["force"];

$options = getopt($shortopts, $longopts);

// one might expect the colon to do this job of checking _required_ options!
if (!array_key_exists('i', $options) || !array_key_exists('s', $options)) {
    exit ('Missing required options' . PHP_EOL);
}

$handle = fopen($options['i'], "r");

if (!$handle) {
    exit (sprintf('Could not open [%s]%s', $options['i'], PHP_EOL));
}

if (!array_key_exists('r', $options) || !$options['r']) {
    $options['r'] = sprintf('%s.out', $options['s']);
}

if (file_exists($options['r']) && !array_key_exists('force', $options)) {
    exit (sprintf('File [%s] already exists, use --force to overwrite%s', $options['r'], PHP_EOL));
}

echo sprintf('Searching for [%s]%s', $options['s'], PHP_EOL);
echo sprintf('Output will be written to [%s]%s', $options['r'], PHP_EOL);

$indenting = 0;
$inode = [];
$found = false;
$i = 0;
while (($line = fgets($handle)) !== false) {
    if (!$found) {
        $found = strstr($line, $options['s']) !== false;
        if ($found) {
            $foundIndenting = getIndenting($line);
            $indenting = $foundIndenting;
        }
    } elseif (strstr($line, 'd/')) {
        $newIndenting = getIndenting($line);
        if ($newIndenting <= $indenting) {
            $found = false;
            break;
        }
        if (strstr($line, 'd/d') && ($newInode = getInode($line)) !== null) {
            $inode[] = $newInode;
        }
    } elseif (($newInode = getInode($line)) !== null) {
        $inode[] = $newInode;
    }
}

if (count($inode)) {
    file_put_contents($options['r'], implode("\n", $inode));
    echo sprintf('[%s] lines wrote to [%s] - the end%s', count($inode), $options['r'], PHP_EOL);
} else {
    exit ('No lines found...' . PHP_EOL);
}

/**
 * @param string $line
 *
 * @return int
 */
function getIndenting($line)
{
    $pattern = '/^([+]*) ?[rd]\//';
    $r = preg_match($pattern, $line, $matches);

    if ($r && count($matches) >= 2) {
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
    $pattern = '/^[+]+ [dr]{1}\/[dr]{1} (\d+-\d+-\d)\:[ \t]+(.*)$/';
    $r = preg_match($pattern, $line, $matches);

    if ($r && count($matches) >= 3) {
        return sprintf('%s:%s', $matches[1], $matches[2]);
    }

    return null;
}
