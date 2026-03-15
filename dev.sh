#!/bin/sh
# Startet alle Entwicklungsserver
#
# Backend:     http://localhost:8080  (PHP + router.php → index.php)
# Verwaltung:  http://localhost:8001
# Website:     http://localhost:8002

trap 'kill 0' INT TERM

php -S localhost:8080 backend/public/router.php &
php -S localhost:8001 -t frontend &
php -S localhost:8002 -t website &

echo "Backend:    http://localhost:8080"
echo "Verwaltung: http://localhost:8001"
echo "Website:    http://localhost:8002"
echo "Beenden mit Ctrl+C"

wait
