What?
=====

This is a personal piece of a larger data recovery operation.

The lost data is on an NTFS disk and we have ubuntu to recover files.

After creating an image of the disk/partition, carving data with [fls](https://wiki.sleuthkit.org/index.php?title=Fls) generates output that can be used to restore files.

The output looks a bit like this:
```
+++++++ r/r 506517-128-3:       IMG_20160929_200337.jpg
```
where `506517-128-3` is an inode. Using the following command 
```
icat -r -f ntfs -i raw /raid/recovery/disk.img 506517-128-3 > "/home/rescue-team/Desktop/recovered/IMG_20160929_200337.jpg"
``` 
we can recover this single file.

More about the above can be found [here](https://help.ubuntu.com/community/DataRecovery).

rescue.php
==========

rescue.php is commandline tool and uses three required parameters and one optional:

```
-i input file
-s subject directory
-o output file (optional)
   contains only a list of recoverable inodes and filenames (06517-128-3:IMG_20160929_200337.jpg)
--force (optional)
   overwrites existing files
```

This script reads a file created by cat-ing `fls` output into it, and searches for a given directory provided by subject `-s`.
Then it stores every line until the end of the directory is reached, subdirectories are also stored.
