#!/bin/bash
nb = 1
for f in *.txt
do
  echo "CCL-ing $f file $nb"
  wcrft-app nkjp.ini -i text -o ccl "$f" -O "../ccl/text$nb.xml"
  nb=$(($nb + 1))
done 
