#!/bin/bash
WATCH_DIR="/home/joeunfish.net/public_html"

inotifywait -m -r -e create,modify,close_write,move "$WATCH_DIR" --format '%w%f' | while read FILE
do
    chown nobody:nobody "$FILE"
done:
