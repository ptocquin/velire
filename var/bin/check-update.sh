#!/bin/bash

git remote update && git status -uno | grep "up to date"

exit $?