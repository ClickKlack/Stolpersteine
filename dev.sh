#!/bin/sh
# Startet Backend- und Frontend-Entwicklungsserver
#
# Backend:  http://localhost:8000  (PHP + router.php → index.php)
# Frontend: http://localhost:8001

trap 'kill 0' INT TERM

php -S localhost:8080 backend/public/router.php &
php -S localhost:8001 -t frontend &

echo "Backend:  http://localhost:8080"
echo "Frontend: http://localhost:8001"
echo "Beenden mit Ctrl+C"

wait
