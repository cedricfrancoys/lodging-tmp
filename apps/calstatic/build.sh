#!/bin/bash
touch manifest.json && rm -f web.app && zip -r web.app *
cat web.app | md5sum | awk '{print $1}'