# BcWpExport 進捗・残件メモ

更新日: 2026-04-13

BcWpExport の実装状況と残件のみを記録する。  
BcWpImport 側は [plugins/BcWpImport/docs/progress.md](../../BcWpImport/docs/progress.md) を参照。

---

## 実装済み

### プラグイン基盤
- `BcWpExportPlugin.php` / `config.php` / `setting.php` / `routes.php`
- Migration: `CreateBcWpExportJobs`
- Entity: `BcWpExportJob` / Table: `BcWpExportJobsTable`

### コマンド
- `BcWpExport.cleanup` — `expires_at` を過ぎたジョブの XML / warning_log / excluded_report と DB レコードを一括削除（`--dry-run` 対応）

### コントローラ（`WpExportsController`）
- `index` — ジョブ一覧画面表示
- `create` — エクスポート条件を受け取りWXR生成・ジョブ保存（同期）
- `download` — 完了ジョブのWXRファイルダウンロード
- `delete` — 個別ジョブ削除（ファイル＋DBレコード）
- `delete_all` — チェックボックスで選択した複数ジョブを一括削除

### サービス
- `WxrWriterService` — DOMDocument を使ったWXR XML生成（名前空間・CDATA・整形・postmeta対応）
- `WpExportService`
  - `createJob` — 設定受取 → データ収集 → WXR生成 → ジョブ保存（同期一括処理）
  - `getJobByToken` — ダウンロード用ジョブ取得
  - `normalizeSettings` — 入力値の正規化（型変換・許容値チェック）
  - `buildSourceSummary` — pages / posts / categories / tags / authors の実件数集計
  - `collectPages` — `export_target` / `content_status` に従って固定ページ収集
  - `collectPosts` — ブログID・著者・カテゴリー・日付範囲フィルタを適用してブログ記事収集
  - `buildPageItems` — 固定ページ → WXR item 配列変換（親子関係・スラッグ・公開状態）
  - `buildPostItems` — ブログ記事 → WXR item 配列変換（カテゴリ・タグ・著者・CDATA本文）
  - `buildAttachmentItems` — アイキャッチ画像を `attachment` post_type として出力、`_thumbnail_id` postmeta付与
  - `buildAuthors` — 投稿者情報（login / display_name / email）収集
  - `buildCategoryMap` / `buildCategoryAncestors` — カテゴリ階層の反映
  - `absolutizeUrls` — 本文中のルート相対URL（`/path`）を絶対URLへ変換
  - `applyDateRange` — date_from / date_to クエリ条件適用
- `WpExportAdminService`
  - `getViewVarsForIndex` — 一覧表示用 view 変数生成（ブログ一覧・カテゴリ・ユーザー一覧含む）
  - `deleteJob` — トークン指定でジョブ削除（ファイル＋DBレコード）
  - `deleteJobs` — 複数トークンを一括削除

### フロントエンド
- `templates/Admin/WpExports/index.php`
  - フィルタ条件（ブログ・カテゴリ・著者・日付範囲）を `bca-collapse` 折りたたみに配置
  - カテゴリはブログ選択に連動して絞り込み
  - 出力オプション（URL変換・メディア出力・サイトメタ・整形）をチェックボックスで設定
  - 生成完了後にリロードなしで結果セクションを表示（件数・ダウンロードボタン）
  - 履歴テーブルをリロードなしで先頭行に追加（JS）
  - 個別削除ボタン・全選択チェックボックス・一括削除ボタン
  - `bc-wp-export__scroll-table` による外枠ボーダー・横スクロール・ヘッダ固定
  - PHP・JS 両側で日時フォーマットを `Y-m-d H:i:s` に統一
- `webroot/js/admin/wp_export.js`
  - 全関数に日本語コメント付き
  - `prependHistoryRow` — エクスポート完了後に履歴行を DOM 先頭に追加
  - `removeHistoryRow` — 削除後に行を DOM から除去・空状態の切り替え
  - `updateBulkDeleteButton` — チェック数に応じて一括削除ボタンの disabled 制御
  - カテゴリ絞り込みの動的更新
  - イベント委譲による個別削除・チェックボックス処理
- `webroot/css/admin/wp_export.css`
  - `.bc-wp-export__scroll-table` — BcCsvImportCore と同等の履歴テーブルスタイル

### テスト
- `tests/TestCase/Service/WxrWriterServiceTest.php`
  - `testBuildEmptyDocument` — 空ドキュメントの RSS タグ確認
  - `testBuildDocument` — authors / items / categories / summary を含む完全なWXR生成確認
- `tests/TestCase/Service/WxrWriterServiceTest.php` に追加済み
  - `testBuildDocumentAttachmentItem` — `attachment` post_type・`wp:attachment_url`・`_thumbnail_id` postmeta
  - `testBuildDocumentPageHierarchy` — 親子ページの `wp:post_parent`
- `tests/TestCase/Service/WpExportServiceTest.php`
  - `absolutizeUrls` の各ケース（ルート相対URL・src属性・絶対URL非変換・プロトコル相対URL非変換・空 siteUrl・末尾スラッシュ・複数箇所）
- `tests/TestCase/Controller/Admin/WpExportsControllerTest.php`
  - `testIndex` — 一覧画面の view 変数と履歴ジョブ表示
  - `testCreate` — エクスポート要求から completed ジョブと出力ファイル生成
  - `testDownload` — 完了ジョブの XML ダウンロード
  - `testDelete` — 個別削除でファイルと DB レコードを除去
  - `testDeleteAll` — 一括削除で複数ファイルと DB レコードを除去
- `tests/TestCase/Command/CleanupCommandTest.php`
  - `testExecuteReturnsWhenNoExpiredJobs` — 対象なし時の正常終了
  - `testExecuteDryRunDoesNotDeleteExpiredJobs` — dry-run では削除せず件数のみ表示
  - `testExecuteDeletesExpiredJobsAndFiles` — 期限切れジョブと関連ファイルの削除

---

## 残件

### v1.0 向け（必須）

- [x] Docker コンテナ内でユニットテストを実行して実動作確認（`WxrWriterServiceTest` の実行）
- [x] `WxrWriterServiceTest` にケースを追加
  - [x] ページ親子関係（`wp:post_parent`）— ContentFolder をスタブページとして含め、フォルダ階層を wp:post_parent で正確に表現
  - [x] アタッチメントアイテム（`attachment` post_type・`wp:attachment_url`・`_thumbnail_id` postmeta）

### v1.1以降（テスト拡充）

- [x] `WpExportServiceTest` の作成 — `absolutizeUrls` の各ケース（ルート相対URL・src属性・絶対URL非変換・プロトコル相対URL非変換・空 siteUrl・末尾スラッシュ・複数箇所）
- [x] `WpExportsControllerTest` の作成 — `index` / `create` / `download` / `delete` / `delete_all`

### 将来対応（大量データ・非同期処理）

- [ ] `status` / `cancel` アクション追加（現状は同期処理のため不要、大量データ時の非同期化の際に追加）
- [ ] Chunked / resumable export（分割生成対応）
- [ ] 除外項目レポートCSVの生成・ダウンロード（`warning_log_path` / `error_log_path` の活用）
- [x] ジョブクリーンアップコマンドの実装 — `expires_at` を参照して期限切れジョブ（XML ファイル・DB レコード）を一括削除する `bin/cake BcWpExport.cleanup` コマンド

### コンテンツタイプ拡張（将来対応）

- [ ] **コンテンツリンク（ContentLink）対応** — `wp:post_type=page` に外部リンク URL を postmeta として含める形でエクスポート予定
- [ ] **カスタムコンテンツ（CustomContent）対応** — WordPress の Custom Post Type としてエクスポート予定（フィールド定義を postmeta にマッピング）

### サービス分離（設計推奨・低優先度）

- [ ] `PageExportService` の分離（現在 `WpExportService` に内包）
- [ ] `BlogPostExportService` の分離（現在 `WpExportService` に内包）

---

## 実装メモ

- `WpExportService::createJob` は同期で一括実行（ジョブは即 `completed` になる）。`status` / `cancel` アクションは非同期化が必要になった時点で追加する。
- WXR の XML 生成は DOMDocument + namespaced element を使用（`content:encoded` / `dc:creator` / `wp:*` 各要素）。
- `include_media_urls` が有効な場合はブログ記事のアイキャッチを `attachment` post_type として items に追加し、親記事に `_thumbnail_id` postmeta を付与する。
- `absolutizeUrls` はルート相対URL（`/path`）のみ対応。プロトコル相対URL（`//example.com`）への対応はv1.1以降。
- `source_summary` のキーは `pages` / `posts` / `categories` / `tags` / `authors` / `total_items`。
- 履歴テーブルの日時は PHP・JS ともに `Y-m-d H:i:s` 形式で統一（ISO 8601 を変換）。
- `BcWpExport.cleanup` は `--dry-run` をサポートし、`output_path` / `warning_log_path` / `excluded_report_path` を削除対象に含める。
- `CleanupCommandTest` / `WxrWriterServiceTest` / `WpExportServiceTest` / `WpExportsControllerTest` は Docker コンテナ内で実行済み（19 tests, 69 assertions）。
