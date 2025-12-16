# Boards テーブル設定ガイド

## テーブル設計

### 1. Boards テーブル（掲示板基本情報）

掲示板の基本情報を管理します。

| カラム | 型 | 説明 |
|--------|-----|------|
| `id` | INT UNSIGNED | 掲示板ID（主キー、自動採番） |
| `name` | VARCHAR(255) | 掲示板名 |
| `slug` | VARCHAR(255) | URL用スラッグ（ユニーク） |
| `description` | TEXT | 掲示板の概要・説明 |
| `created_at` | DATETIME | 作成日時（デフォルト: CURRENT_TIMESTAMP） |
| `updated_at` | DATETIME | 更新日時（デフォルト: CURRENT_TIMESTAMP、更新時に自動更新） |

**インデックス:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `slug`

---

### 2. BoardComments テーブル（掲示板への書き込み・コメント）

掲示板に投稿されたコメント・書き込みを管理します。

| カラム | 型 | 説明 |
|--------|-----|------|
| `id` | INT UNSIGNED | コメント番号（主キー、自動採番） |
| `board_id` | INT UNSIGNED | 掲示板ID（外部キー → Boards.id） |
| `user_id` | INT UNSIGNED | 投稿者ID（外部キー → Users.id、任意） |
| `content` | TEXT | コメント内容 |
| `created_at` | DATETIME | 投稿日時（デフォルト: CURRENT_TIMESTAMP） |
| `updated_at` | DATETIME | 更新日時（デフォルト: CURRENT_TIMESTAMP、更新時に自動更新） |

**インデックス:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `board_id` → Boards.id (ON DELETE CASCADE)
- 検索用: `board_id`, `user_id`, `created_at`

**外部キー制約:**
- `board_id`: Boards テーブルの id を参照。掲示板が削除された場合、関連するコメントもすべて削除されます。
- `user_id`: Users テーブルの id を参照（任意）。投稿者が削除された場合、user_id は NULL になります。

---

## テーブル作成手順

### 方法1: PHPマイグレーションスクリプトを使用（推奨）

#### 前提条件
- XAMPP が起動している
- PHP がインストール されている（XAMPP に含まれる）
- MySQL/MariaDB が起動している

#### 手順

**1. ファイルの確認**

`p:/works/` ディレクトリに以下のファイルが存在することを確認:
- `create_boards_table.sql` — SQLスクリプト
- `migrate_create_boards.php` — マイグレーション実行スクリプト

**2. DBのカスタマイズ（必要に応じて）**

`migrate_create_boards.php` をテキストエディタで開き、DB接続設定を確認・修正:

```php
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'snsdb';  // 実際のDB名に合わせて修正
```

**3. PowerShell でスクリプトを実行**

PowerShell を開き、以下のコマンドを実行:

```powershell
cd p:\works
php migrate_create_boards.php
```

**期待される出力:**
```
Boards テーブルの作成が完了しました（または既に存在します）。
```

エラーが出た場合は、メッセージを確認し、以下の原因を調査してください:
- DB接続情報が正しいか
- DB名が正しいか
- MySQL が起動しているか

---

### 方法2: MySQL クライアントで直接実行

#### 手順

**1. PowerShell でMySQL コマンドラインツールを起動**

```powershell
cd p:\works
mysql -u root -p < create_boards_table.sql
```

パスワード入力を求められたら Enter キーを押してください（デフォルトではパスワードなし）。

または、DB名を指定する場合:

```powershell
mysql -u root snsdb < create_boards_table.sql
```

**期待される出力:**
```
(エラーがない場合、何も出力されない)
```

---

### 方法3: phpMyAdmin で実行

**1. phpMyAdmin を開く**
- ブラウザで `http://localhost/phpmyadmin` にアクセス

**2. 実行するDB を選択**
- 左側メニューから `snsdb` (または使用するDB) を選択

**3. SQL を実行**
- トップメニューから「SQL」タブをクリック
- `create_boards_table.sql` の内容をコピー&ペースト
- または、ファイルをアップロード
- 「実行」ボタンをクリック

---

## テーブルの確認

作成後、以下のコマンドで テーブルが作成されたことを確認できます:

### MySQL コマンドラインで確認

```powershell
mysql -u root -e "USE snsdb; SHOW TABLES LIKE 'Board%';"
```

**期待される出力:**
```
+------------------+
| Tables_in_snsdb  |
+------------------+
| BoardComments    |
| Boards           |
+------------------+
```

### テーブルスキーマの詳細を確認

```powershell
mysql -u root -e "USE snsdb; DESCRIBE Boards; DESCRIBE BoardComments;"
```

---

## データ挿入例

テーブル作成後、サンプルデータを挿入して動作確認ができます:

```sql
-- Boards テーブルに掲示板を追加
INSERT INTO `Boards` (`name`, `slug`, `description`)
VALUES
  ('一般討論', 'general', '一般的なトピックについての討論'),
  ('アニメ雑談', 'anime-chat', 'アニメについての自由な雑談'),
  ('技術情報', 'tech-info', 'プログラミング・Web技術に関する情報交換');

-- BoardComments テーブルにコメントを追加
INSERT INTO `BoardComments` (`board_id`, `user_id`, `content`)
VALUES
  (1, 1, 'はじめましてよろしくお願いします。'),
  (1, 2, 'こんにちは！何かお手伝いできることはありますか？'),
  (2, 1, '最新のアニメ、どれが面白いですか？'),
  (3, 3, 'JavaScriptの最新トレンドについて共有します。');
```

---

## トラブルシューティング

### エラー: "テーブルが既に存在します"
→ これは正常です。SQL内で `CREATE TABLE IF NOT EXISTS` を使用しているため、既に存在する場合は作成スキップされます。

### エラー: "Access denied for user 'root'@'localhost'"
→ DB接続情報（ユーザー、パスワード）を確認してください。XAMPP のデフォルトは `root` / パスワードなし です。

### エラー: "Unknown database 'snsdb'"
→ 使用するDB名を確認し、スクリプト内の `$dbName` を正しく設定してください。

### エラー: "Cannot add or update a child row: a foreign key constraint fails"
→ 外部キー制約により、存在しない `board_id` でコメントを挿入できません。先に Boards テーブルにレコードを挿入してください。

---

## 参考リンク

- [MySQL 公式ドキュメント](https://dev.mysql.com/doc/)
- [PDO – PHP 公式ドキュメント](https://www.php.net/manual/en/class.pdo.php)
