#!/bin/bash

echo "info: nginx non-daemon startup"
nginx -c /etc/nginx/nginx.conf
tail -f /dev/null