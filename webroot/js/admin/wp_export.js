(function () {
    'use strict';

    /**
     * CSRF トークン付きで FormData を POST し、レスポンス JSON を返す
     */
    async function postForm(url, formData, csrfToken) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        });

        const text = await response.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch (error) {
            if (!response.ok) {
                throw new Error(text.slice(0, 300) || 'Server returned a non-JSON error response.');
            }
            throw new Error('サーバー応答の JSON 解析に失敗しました。');
        }

        if (!response.ok) {
            throw new Error(data?.message || 'Request failed.');
        }
        return data;
    }

    /**
     * エラーメッセージを表示・非表示する
     * message が空文字の場合は非表示にする
     */
    function setError(message) {
        const errorBox = document.getElementById('js-export-error');
        errorBox.textContent = message;
        errorBox.style.display = message ? 'block' : 'none';
    }

    /**
     * エクスポート完了後の結果セクションを更新して表示する
     * フォームセクションを非表示にし、履歴テーブルの先頭に行を追加する
     */
    function setResult(result, adminBase) {
        const job = result.result ?? result;
        let summary = {};
        if (job.source_summary) {
            if (typeof job.source_summary === 'string') {
                try {
                    summary = JSON.parse(job.source_summary);
                } catch (error) {
                    summary = {};
                }
            } else if (typeof job.source_summary === 'object') {
                summary = job.source_summary;
            }
        }

        document.getElementById('js-res-filename').textContent = job.output_filename || '-';
        document.getElementById('js-res-total').textContent = (summary.total_items != null ? Number(summary.total_items).toLocaleString() : (job.success_count != null ? Number(job.success_count).toLocaleString() : '-')) + ' 件';
        document.getElementById('js-res-pages').textContent = summary.pages != null ? Number(summary.pages).toLocaleString() + ' 件' : '-';
        document.getElementById('js-res-posts').textContent = summary.posts != null ? Number(summary.posts).toLocaleString() + ' 件' : '-';
        document.getElementById('js-res-expires').textContent = formatDatetime(job.expires_at);

        const dlLink = document.getElementById('js-export-download');
        dlLink.href = adminBase + '/download/' + job.job_token;

        document.getElementById('js-form-section').style.display = 'none';
        document.getElementById('js-result-section').style.display = '';

        prependHistoryRow(job, adminBase);
    }

    /**
     * ISO 8601 等の日時文字列を "YYYY-MM-DD HH:MM:SS" 形式に変換する
     * パース不能な値はそのまま返す
     */
    function formatDatetime(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (isNaN(d.getTime())) return value;
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    /**
     * エクスポート完了後に履歴テーブルの先頭に新しい行を追加する
     * 「履歴なし」メッセージを非表示にしてテーブルと一括削除ボタンを表示する
     */
    function prependHistoryRow(job, adminBase) {
        const tbody = document.getElementById('js-history-tbody');
        const table = document.getElementById('js-history-table');
        const emptyNote = document.getElementById('js-history-empty');
        const bulkActions = document.getElementById('js-history-bulk-actions');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.dataset.jobToken = job.job_token || '';
        const endedAt = formatDatetime(job.ended_at);
        const expires = formatDatetime(job.expires_at);
        const count = job.success_count != null ? Number(job.success_count).toLocaleString() + ' 件' : '-';
        const filename = job.output_filename || '-';
        const token = job.job_token || '';

        // ジョブトークンがある場合のみダウンロードリンクを生成する
        const dlCell = token
            ? `<a href="${adminBase}/download/${token}" class="bca-btn bca-actions__item" data-bca-btn-type="download">ダウンロード</a>`
            : '';

        tr.innerHTML =
            '<td class="bca-table-listup__tbody-td"><input type="checkbox" class="js-history-check" value="' + token + '"></td>' +
            '<td class="bca-table-listup__tbody-td">' + endedAt + '</td>' +
            '<td class="bca-table-listup__tbody-td">' + (job.status || 'completed') + '</td>' +
            '<td class="bca-table-listup__tbody-td">' + count + '</td>' +
            '<td class="bca-table-listup__tbody-td">' + filename + '</td>' +
            '<td class="bca-table-listup__tbody-td">' + expires + '</td>' +
            '<td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--actions">' + dlCell + '<button type="button" class="bca-btn bca-actions__item js-history-delete-btn" data-bca-btn-type="delete" data-job-token="' + token + '">削除</button></td>';
        tbody.insertBefore(tr, tbody.firstChild);

        if (emptyNote) emptyNote.style.display = 'none';
        if (table) table.style.display = '';
        if (bulkActions) bulkActions.style.display = '';
    }

    /**
     * 指定トークンの履歴行を DOM から削除する
     * 行がゼロになった場合はテーブルを非表示にして「履歴なし」メッセージを表示する
     */
    function removeHistoryRow(token) {
        const tbody = document.getElementById('js-history-tbody');
        const table = document.getElementById('js-history-table');
        const emptyNote = document.getElementById('js-history-empty');
        const bulkActions = document.getElementById('js-history-bulk-actions');
        if (!tbody) return;

        const row = tbody.querySelector(`tr[data-job-token="${CSS.escape(token)}"]`);
        if (row) row.remove();

        if (tbody.querySelectorAll('tr').length === 0) {
            if (table) table.style.display = 'none';
            if (bulkActions) bulkActions.style.display = 'none';
            if (emptyNote) emptyNote.style.display = '';
        }
    }

    /**
     * 個別削除: API を呼んで対象トークンの行を削除する
     */
    async function deleteJob(token, adminBase, csrfToken) {
        const fd = new FormData();
        await postForm(adminBase + '/delete/' + token, fd, csrfToken);
        removeHistoryRow(token);
    }

    /**
     * 一括削除: tokens[] を POST し、成功後に各行を削除する
     */
    async function deleteAllJobs(tokens, adminBase, csrfToken) {
        const fd = new FormData();
        tokens.forEach((t) => fd.append('tokens[]', t));
        await postForm(adminBase + '/delete_all', fd, csrfToken);
        tokens.forEach((t) => removeHistoryRow(t));
    }

    /**
     * チェック数に応じて一括削除ボタンの disabled を更新する
     */
    function updateBulkDeleteButton() {
        const btn = document.getElementById('js-history-delete-all-btn');
        if (!btn) return;
        const checked = document.querySelectorAll('.js-history-check:checked');
        btn.disabled = checked.length === 0;
    }

    /**
     * フォームの各入力値を FormData にまとめて返す
     */
    function buildFormData() {
        const formData = new FormData();
        formData.append('export_target', document.getElementById('export-target').value);
        formData.append('content_status', document.getElementById('content-status').value);
        formData.append('blog_content_id', document.getElementById('blog-content-id').value);
        formData.append('author_id', document.getElementById('author-id').value);
        formData.append('category_id', document.getElementById('category-id').value);
        formData.append('date_from', document.getElementById('date-from').value);
        formData.append('date_to', document.getElementById('date-to').value);
        // チェックボックスはオンの場合のみ送信する
        if (document.getElementById('include-site-meta').checked) {
            formData.append('include_site_meta', '1');
        }
        if (document.getElementById('include-media-urls').checked) {
            formData.append('include_media_urls', '1');
        }
        if (document.getElementById('absolute-url').checked) {
            formData.append('absolute_url', '1');
        }
        if (document.getElementById('pretty-print').checked) {
            formData.append('pretty_print', '1');
        }
        return formData;
    }

    /**
     * ブログ選択に連動してカテゴリプルダウンを更新する
     * window.bcWpExportConfig.categoriesByBlog から対象ブログのカテゴリを取得する
     */
    function updateCategories(blogId) {
        const cats = (window.bcWpExportConfig.categoriesByBlog || {})[blogId] || {};
        const categorySelect = document.getElementById('category-id');
        categorySelect.innerHTML = '<option value="">指定なし</option>';
        Object.entries(cats).forEach(function ([id, title]) {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = title;
            categorySelect.appendChild(opt);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const config = window.bcWpExportConfig || {};
        const button = document.getElementById('js-create-export-btn');
        const blogSelect = document.getElementById('blog-content-id');

        // ブログ変更時にカテゴリを絞り込む
        if (blogSelect) {
            blogSelect.addEventListener('change', function () {
                updateCategories(this.value);
            });
            updateCategories(blogSelect.value);
        }

        // 「別のエクスポートを実行」ボタン: 結果を隠してフォームを再表示する
        const restartBtn = document.getElementById('js-restart-btn');
        if (restartBtn) {
            restartBtn.addEventListener('click', function () {
                document.getElementById('js-result-section').style.display = 'none';
                document.getElementById('js-form-section').style.display = '';
            });
        }

        // 全選択チェックボックス
        const checkAll = document.getElementById('js-history-check-all');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.js-history-check').forEach((cb) => {
                    cb.checked = checkAll.checked;
                });
                updateBulkDeleteButton();
            });
        }

        // 個別チェックボックス・削除ボタンはイベント委譲で tbody に登録する
        // （JS で動的追加した行にも対応するため）
        const tbody = document.getElementById('js-history-tbody');
        if (tbody) {
            // 個別チェックボックスの変更で一括削除ボタンを更新する
            tbody.addEventListener('change', function (e) {
                if (e.target.classList.contains('js-history-check')) {
                    updateBulkDeleteButton();
                    // 1 件でも外れたら全選択チェックを解除する
                    if (!e.target.checked && checkAll) checkAll.checked = false;
                }
            });

            // 個別削除ボタン
            tbody.addEventListener('click', async function (e) {
                const btn = e.target.closest('.js-history-delete-btn');
                if (!btn) return;
                if (!confirm('この履歴を削除しますか？')) return;
                const token = btn.dataset.jobToken;
                btn.disabled = true;
                try {
                    await deleteJob(token, config.adminBase, config.csrfToken);
                } catch (err) {
                    alert('削除に失敗しました: ' + err.message);
                    btn.disabled = false;
                }
            });
        }

        // 一括削除ボタン
        const deleteAllBtn = document.getElementById('js-history-delete-all-btn');
        if (deleteAllBtn) {
            deleteAllBtn.addEventListener('click', async function () {
                const tokens = Array.from(document.querySelectorAll('.js-history-check:checked')).map((cb) => cb.value);
                if (tokens.length === 0) return;
                if (!confirm(tokens.length + ' 件の履歴を削除しますか？')) return;
                deleteAllBtn.disabled = true;
                try {
                    await deleteAllJobs(tokens, config.adminBase, config.csrfToken);
                    if (checkAll) checkAll.checked = false;
                } catch (err) {
                    alert('削除に失敗しました: ' + err.message);
                    deleteAllBtn.disabled = false;
                }
            });
        }

        if (!button) {
            return;
        }

        // WXR 生成ボタン: フォームを POST してジョブを作成し結果を表示する
        button.addEventListener('click', async function () {
            setError('');
            button.disabled = true;
            button.textContent = 'WXR を生成中...';

            try {
                const result = await postForm(config.adminBase + '/create', buildFormData(), config.csrfToken);
                setResult(result, config.adminBase);
            } catch (error) {
                setError(error.message || 'エクスポートに失敗しました。');
            } finally {
                button.disabled = false;
                button.textContent = 'WXR を生成';
            }
        });
    });
})();
