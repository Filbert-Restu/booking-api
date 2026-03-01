#!/bin/bash
# Wrapper to start Laravel dev server with custom PHP ini settings
cd /home/tim8/booking-mainrepo/booking-api
exec php -d upload_max_filesize=50M -d post_max_size=100M -d max_execution_time=300 -S 0.0.0.0:20082 public/index.php
