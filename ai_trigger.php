<?php

exec("php /opt/www/ai/ai_worker_v2.php > /dev/null 2>&1 &");

echo "AI worker started";