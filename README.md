# wp-google-login

[GitHubリポジトリはこちら](https://github.com/wadatch/wp-google-login)

WordPressにGoogleログイン（OAuth/One Tap）を簡単に追加できるプラグインです。GoogleアカウントでのログインボタンやOne Tapによる即時ログイン、メールアドレス一致による既存ユーザー認証・新規ユーザー自動作成、ドメイン→ロール自動割当、管理画面からの設定、開発用フックなどを提供します。

## 主な機能
- WordPressログイン画面に「Googleでログイン」ボタンを追加
- Google One Tapによる即時ログイン対応
- メールアドレス一致で既存ユーザー認証、なければ自動作成
- Google sub IDによるユーザー紐付け
- ドメイン→ロール自動割当（管理画面で設定可）
- 管理画面からClient ID/Secretやマッピングを設定
- ログイン後に独自処理を追加できる`gsl_after_login`フック
- WP_DEBUG有効時はエラーログ出力

## インストール
### 前提
- PHP 7.4以上
- Composer

### 手順
1. このリポジトリをクローンまたはダウンロード
2. プラグインディレクトリへ配置
3. 依存ライブラリをインストール

```bash
git clone https://github.com/wadatch/wp-google-login.git
cd wp-google-login
composer install --no-dev --optimize-autoloader
```

4. WordPress管理画面「プラグイン」から有効化

#### ZIPアップロードの場合
1. 上記手順で`vendor/`を含めてZIP化
2. WordPress管理画面「プラグイン > 新規追加 > アップロード」からZIPをアップロード

## 使い方・設定
1. WordPress管理画面 → **設定 > wp-google-login** へ移動
2. Google Cloud Consoleで取得したOAuth **Client ID / Client Secret** を入力
3. （任意）**Domain→Role Mapping**欄に`example.com=editor`の形式で1行ずつ記入（最初に一致したものが適用）

## 開発・コントリビュート
- プルリクエスト歓迎です
- 依存パッケージは`composer install`で導入
- テストやIssue報告も歓迎
- 拡張用フック: `gsl_after_login`（ログイン後に呼ばれます）

## ライセンス
MIT License

詳細は[LICENSE](./LICENSE)を参照してください。

---
© 2025 wadatch
