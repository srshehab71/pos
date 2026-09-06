@echo off
echo Starting GitHub Sync...

:: ১. সব ফাইল অ্যাড করা
git add .

:: ২. বর্তমান তারিখ ও সময় দিয়ে কমিট মেসেজ তৈরি করা
set msg=Update on %date% %time%
git commit -m "%msg%"

:: ৩. গিটহাবে পুশ করা
git push

echo.
echo =======================================
echo GitHub Update Successfull!
echo =======================================
pause