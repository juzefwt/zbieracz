#!/bin/bash
nb = 1
for f in *.txt
do
  echo "CCL-ing $f file into $f.xml"
  wcrft-app nkjp.ini -i text -o ccl "$f" -O "../ccl/$f.xml"
  nb=$(($nb + 1))
done 
