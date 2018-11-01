#!/bin/bash

FILE="./bin/commands@$1"

echo $FILE >> log.txt

if [[ -e $FILE ]]; then
	rm -f $FILE 2>> log.txt
fi