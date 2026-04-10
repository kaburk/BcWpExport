# BcWpExport 詳細設計

## 目的

- baserCMS5 上の主要コンテンツを WordPress が取り込みやすい WXR として出力する。
- 完全移行よりも、WordPress へ渡せる素材を安全に出力することを優先する。

## 想定ユースケース

- baserCMS の固定ページとブログを WordPress に移行したい。
- baserCMS サイトの一部だけを WordPress に移したい。
- 移行前にテスト用 WXR を複数回生成したい。

## MVP 対応範囲

### 対象

- 固定ページ
- ブログ記事
- ブログカテゴリー
- ブログタグ
- 公開済みユーザー情報のうち投稿者識別に必要な最小情報
- アイキャッチや本文内画像の URL 情報

### 初版では対象外または限定対応

- メールフォーム
- ウィジェットエリア
- テーマ設定
- カスタムコンテンツ
- コメント
- 添付ファイル本体の同梱
- ナビゲーションメニュー
- SEO プラグイン固有設定

## baserCMS から WXR へのマッピング方針

| baserCMS | WXR / WordPress 側 | 備考 |
|---|---|---|
| 固定ページ | page | 親子関係、スラッグ、公開状態を可能な範囲で維持 |
| ブログ記事 | post | 投稿日、更新日、本文、抜粋、スラッグ、著者を出力 |
| ブログカテゴリー | category | 階層構造があれば維持 |
| ブログタグ | post_tag | タクソノミーとして出力 |
| 投稿者 | author | WordPress 側で紐付けや再割当の前提 |
| 本文内画像 | post_content 内 URL | URL が有効な環境での移行を前提 |

## エクスポート対象の絞り込み

- コンテンツ種別
  - 全件
  - 固定ページのみ
  - ブログのみ
- ブログコンテンツ選択
  - 特定ブログのみ
- 公開状態
  - 公開のみ
  - 非公開を含む
  - 下書きを含む
- 日付範囲
- 著者
- カテゴリー

## 出力オプション案

- 文字コードは UTF-8 固定
- 改行コードは LF 固定
- XML 整形出力のオン・オフ
- 本文中の baserCMS 内部 URL を絶対 URL 化する
- メディア URL を公開 URL のまま出力する
- サイト情報を channel 要素に含める
- excluded_items レポートを同時生成する

## ジョブ処理方針

- データ量が多いサイトでも処理できるよう、ジョブテーブルを持つ。
- 生成中ジョブ、完了ジョブ、失敗ジョブを一覧管理する。
- XML 生成は段階的に行い、中断再開可能とする。
- 出力結果は一時ファイルとして保持し、保持期限を設定する。

## ディレクトリ構成案

```text
plugins/BcWpExport/
├── README.md
├── VERSION.txt
├── LICENSE.txt
├── composer.json
├── config.php
├── config/
│   ├── routes.php
│   ├── setting.php
│   └── Migrations/
│       └── YYYYMMDDHHMMSS_CreateBcWpExportJobs.php
├── src/
│   ├── BcWpExportPlugin.php
│   ├── Controller/
│   │   └── Admin/
│   │       └── WpExportsController.php
│   ├── Model/
│   │   ├── Entity/
│   │   │   └── BcWpExportJob.php
│   │   └── Table/
│   │       └── BcWpExportJobsTable.php
│   ├── Service/
│   │   ├── Admin/
│   │   │   └── WpExportAdminService.php
│   │   ├── WxrWriterService.php
│   │   ├── WpExportService.php
│   │   ├── PageExportService.php
│   │   └── BlogPostExportService.php
│   └── Utility/
│       ├── WxrWriter.php
│       └── WxrXmlBuilder.php
├── templates/
│   └── Admin/
│       └── WpExports/
│           └── index.php
├── webroot/
│   ├── css/
│   │   └── admin/
│   │       └── wp_export.css
│   └── js/
│       └── admin/
│           └── wp_export.js
└── tests/
    └── TestCase/
        ├── Controller/
        │   └── Admin/
        │       └── WpExportsControllerTest.php
        └── Service/
            ├── WxrWriterServiceTest.php
            └── WpExportServiceTest.php
```

## 初期ファイル一覧

| ファイル | 必要度 | 役割 |
|---|---|---|
| README.md | 必須 | 概要、導入手順、制約を記載 |
| VERSION.txt | 必須 | プラグイン版管理 |
| LICENSE.txt | 必須 | ライセンス明示 |
| composer.json | 必須 | CakePHP プラグインとしての autoload 設定 |
| config.php | 必須 | プラグイン名、説明、作者情報 |
| config/routes.php | 任意 | 管理画面外の専用ルートが必要になったとき用。初期は空でもよい |
| config/setting.php | 必須 | 管理画面メニュー、デフォルト設定 |
| config/Migrations/CreateBcWpExportJobs.php | 必須 | エクスポートジョブ管理テーブル作成 |
| src/BcWpExportPlugin.php | 必須 | プラグイン本体 |
| src/Controller/Admin/WpExportsController.php | 必須 | index、create、process、download、status、delete などの入口 |
| src/Model/Entity/BcWpExportJob.php | 必須 | ジョブエンティティ |
| src/Model/Table/BcWpExportJobsTable.php | 必須 | ジョブ保存、フィルタ条件保持 |
| src/Service/Admin/WpExportAdminService.php | 推奨 | 一覧表示用の view 変数、件数サマリ生成 |
| src/Service/WxrWriterService.php | 必須 | XML 出力、ファイル生成、検証 |
| src/Service/WpExportService.php | 必須 | ジョブ全体の進行管理 |
| src/Service/PageExportService.php | 推奨 | 固定ページ出力責務を分離 |
| src/Service/BlogPostExportService.php | 推奨 | ブログ記事出力責務を分離 |
| src/Utility/WxrWriter.php | 推奨 | WXR の要素書き出し共通化 |
| src/Utility/WxrXmlBuilder.php | 推奨 | channel / item の XML 組み立て補助 |
| templates/Admin/WpExports/index.php | 必須 | フィルタ指定、ジョブ一覧、ダウンロード、履歴表示 |
| webroot/css/admin/wp_export.css | 任意 | 参考 UI の差分スタイル |
| webroot/js/admin/wp_export.js | 推奨 | 実行開始、進捗更新、ダウンロード導線 |

## 最小スタート構成

- README.md
- VERSION.txt
- LICENSE.txt
- composer.json
- config.php
- config/setting.php
- config/Migrations/CreateBcWpExportJobs.php
- src/BcWpExportPlugin.php
- src/Controller/Admin/WpExportsController.php
- src/Model/Entity/BcWpExportJob.php
- src/Model/Table/BcWpExportJobsTable.php
- src/Service/WxrWriterService.php
- src/Service/WpExportService.php
- templates/Admin/WpExports/index.php
- webroot/js/admin/wp_export.js
- tests/TestCase/Service/WxrWriterServiceTest.php

## ジョブテーブル詳細設計

### 想定テーブル名

- bc_wp_export_jobs

### 用途

- 出力対象とフィルタ条件を保持する。
- WXR 生成の進捗と結果ファイルを保持する。
- 除外項目や警告のレポートをダウンロードできるようにする。

### カラム案

| カラム | 型 | 必須 | 説明 |
|---|---|---|---|
| id | int PK AUTO | ○ | 主キー |
| job_token | varchar(255) | ○ | ジョブ識別子。ユニーク |
| status | varchar(30) | ○ | pending / processing / completed / failed / cancelled |
| phase | varchar(30) | ○ | collect / build / finalize |
| export_target | varchar(30) | ○ | all / posts / pages |
| blog_content_id | int | - | 特定ブログのみ出力するときに利用 |
| author_id | int | - | 著者絞り込み |
| category_id | int | - | カテゴリー絞り込み |
| content_status | varchar(30) | ○ | published / all / draft_included |
| date_from | datetime | - | 出力開始日 |
| date_to | datetime | - | 出力終了日 |
| include_site_meta | boolean | ○ | channel 情報出力の有無 |
| include_media_urls | boolean | ○ | 本文内・アイキャッチ URL を出力するか |
| absolute_url | boolean | ○ | 内部URLを絶対URL化するか |
| pretty_print | boolean | ○ | XML 整形出力するか |
| source_summary | text | - | 抽出対象の件数サマリ JSON |
| export_settings | text | - | 出力条件 JSON |
| output_filename | varchar(255) | - | ダウンロード用ファイル名 |
| output_path | varchar(255) | - | 生成 WXR ファイルパス |
| warning_log_path | varchar(255) | - | 警告ログ JSON Lines |
| excluded_report_path | varchar(255) | - | 未出力項目レポート CSV |
| build_position | bigint | - | 生成再開用の位置 |
| total_items | int | ○ | 出力対象総件数 |
| processed | int | ○ | 処理済件数 |
| success_count | int | ○ | 出力できた item 件数 |
| skip_count | int | ○ | スキップ件数 |
| warning_count | int | ○ | 警告件数 |
| error_count | int | ○ | エラー件数 |
| expires_at | datetime | ○ | 生成物保持期限 |
| started_at | datetime | - | ジョブ開始日時 |
| ended_at | datetime | - | ジョブ終了日時 |
| created | datetime | ○ | 作成日時 |
| modified | datetime | ○ | 更新日時 |

### source_summary に持たせる内容

```json
{
  "pages": 42,
  "posts": 186,
  "categories": 14,
  "tags": 37,
  "authors": 5,
  "excluded_candidates": 9
}
```

### export_settings に持たせる内容

```json
{
  "export_target": "all",
  "blog_content_id": 2,
  "author_id": null,
  "category_id": null,
  "content_status": "published",
  "date_from": null,
  "date_to": null,
  "include_site_meta": true,
  "include_media_urls": true,
  "absolute_url": true,
  "pretty_print": false
}
```

### 状態遷移

| status | phase | 意味 |
|---|---|---|
| pending | collect | 抽出条件保存直後 |
| processing | collect | 対象件数集計・対象抽出中 |
| processing | build | WXR item 組み立て中 |
| processing | finalize | XML 完成、レポート生成中 |
| completed | finalize | WXR 生成完了 |
| failed | collect/build/finalize | 処理失敗 |
| cancelled | collect/build/finalize | ユーザー中断 |

### インデックス案

- UNIQUE job_token
- INDEX status
- INDEX phase
- INDEX blog_content_id
- INDEX expires_at
- INDEX created

### 実装メモ

- collect と build を分けることで、開始前に総件数や対象件数を一覧に出しやすい。
- excluded_report_path は export 側で重要なので初期から持たせる。
- source_summary は履歴一覧の何件を対象にしたかの表示にも流用できる。

## migration たたき台

### 方針

- 初期 migration はジョブテーブル作成のみに絞る。
- JSON 保持カラムは text にして、DB 方言依存を避ける。
- 1 migration 1 テーブルで開始する。

### ファイル名案

- config/Migrations/YYYYMMDDHHMMSS_CreateBcWpExportJobs.php

### 実装イメージ

```php
<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateBcWpExportJobs extends BcMigration
{
    public function up()
    {
        $this->table('bc_wp_export_jobs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('job_token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending'])
            ->addColumn('phase', 'string', ['limit' => 30, 'null' => false, 'default' => 'collect'])
            ->addColumn('export_target', 'string', ['limit' => 30, 'null' => false, 'default' => 'all'])
            ->addColumn('blog_content_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('author_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('category_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('content_status', 'string', ['limit' => 30, 'null' => false, 'default' => 'published'])
            ->addColumn('date_from', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('date_to', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('include_site_meta', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('include_media_urls', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('absolute_url', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('pretty_print', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('source_summary', 'text', ['null' => true, 'default' => null])
            ->addColumn('export_settings', 'text', ['null' => true, 'default' => null])
            ->addColumn('output_filename', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('output_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('warning_log_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('excluded_report_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('build_position', 'biginteger', ['null' => true, 'default' => 0])
            ->addColumn('total_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('processed', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('success_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('skip_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('warning_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('error_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('ended_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['job_token'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['phase'])
            ->addIndex(['blog_content_id'])
            ->addIndex(['expires_at'])
            ->addIndex(['created'])
            ->create();
    }

    public function down()
    {
        $this->table('bc_wp_export_jobs')->drop()->save();
    }
}
```

### 補足

- output_filename と output_path は生成完了まで null を許容する。
- source_summary は collect フェーズ完了時に埋まる前提にする。
- category_id は初期は単一カテゴリー絞り込みで開始し、複数対応は後続でもよい。

### migration 作成時の注意点

- enum は使わず string で表現する。
- 将来の値追加に備え、status や phase を DB 制約で固めすぎない。
- bigint の position 系は 0 初期値で揃える。
- addIndex() は一覧検索と期限削除で使うものだけに留める。
