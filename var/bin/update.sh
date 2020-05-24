#!/bin/bash

git pull --rebase --autostash --stat origin master
rm -rf ../var/cache/*