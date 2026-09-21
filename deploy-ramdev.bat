@echo off
REM ============================================================
REM  UrbanPOS — Server Deploy Script (Ramdevcar Shop)
REM  Server  : 178.16.136.126:65002
REM  Project : /home/u495274500/domains/ramdevcar.shop/public_html/pos
REM ============================================================

SET SSH_HOST=178.16.136.126
SET SSH_PORT=65002
SET SSH_USER=u495274500
SET SSH_PASS=Chandak@1388
SET SSH_HKEY=SHA256:pQm/IdgXkQ3aGql7tcYLgHtMdZpupu+15Zz8Z/cJLcQ
SET PROJECT=/home/u495274500/domains/ramdevcar.shop/public_html/pos

echo.
echo ============================================
echo  UrbanPOS Ramdevcar Shop Server Deploy
echo ============================================
echo.

plink -pw "%SSH_PASS%" -P %SSH_PORT% -batch -hostkey "%SSH_HKEY%" %SSH_USER%@%SSH_HOST% ^
  "cd %PROJECT% && echo '=== GIT PULL ===' && git pull origin main && echo '=== MIGRATE ===' && php artisan migrate --force && echo '=== SEED ===' && php artisan db:seed --class=FormFieldValidationSeeder --force && echo '=== CACHE CLEAR ===' && php artisan route:clear && php artisan config:clear && php artisan view:clear && php artisan cache:clear && echo '=== DONE ==='"

echo.
echo Deploy to Ramdevcar finished!
pause
