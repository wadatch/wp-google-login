#!/bin/bash
set -e

PLUGIN_NAME="wp-google-login"
DIST_DIR="dist"
BUILD_DIR="${PLUGIN_NAME}_build"
ZIP_FILE="${PLUGIN_NAME}.zip"

# クリーンアップ
echo "[build] Cleaning up..."
rm -rf "$BUILD_DIR" "$DIST_DIR/$ZIP_FILE"

# ビルド用ディレクトリ作成
echo "[build] Preparing build directory..."
mkdir -p "$BUILD_DIR"

# 必要ファイル・ディレクトリをコピー
echo "[build] Copying files..."
rsync -av --exclude "$DIST_DIR" --exclude "$BUILD_DIR" --exclude ".git" --exclude "node_modules" --exclude "*.zip" ./ "$BUILD_DIR/"

# 依存ライブラリをインストール
echo "[build] Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --working-dir="$BUILD_DIR"

# distディレクトリ作成
echo "[build] Creating dist directory..."
mkdir -p "$DIST_DIR"

# ZIP化
echo "[build] Creating zip..."
cd "$BUILD_DIR"
zip -r "../$DIST_DIR/$ZIP_FILE" . -x "dist/*" -x "$BUILD_DIR/*"
cd ..

# クリーンアップ
echo "[build] Cleaning up build directory..."
rm -rf "$BUILD_DIR"

echo "[build] Done! => $DIST_DIR/$ZIP_FILE" 