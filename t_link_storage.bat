@echo off
if not exist "public\storage" (
    mklink /J "public\storage" "..\storage\app\public"
    echo Created storage link
) else (
    echo Storage link already exists
)
dir public\storage\uploads\1\screenshot\*.png 2>nul || echo No PNG files found
