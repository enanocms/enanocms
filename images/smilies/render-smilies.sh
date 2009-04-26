#!/bin/bash
for f in *.svg; do
    echo Converting $f
    fname=`echo $f | cut -d '.' -f 1`
    rm -f $fname.png
    inkscape -z -f $f -w 22 -h 22 -e ./$fname.png
done
