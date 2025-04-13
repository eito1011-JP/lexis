import React from 'react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import HomepageFeatures from '@site/src/components/HomepageFeatures';
import Heading from '@theme/Heading';
import { useSession } from '@site/src/contexts/SessionContext';
import { apiClient } from '@site/src/components/admin/api/client';
import { API_CONFIG } from '@site/src/components/admin/api/config';

import styles from './index.module.css';

function HomepageHeader() {
  const { siteConfig } = useDocusaurusContext();
  const { user } = useSession();
  const [showBranchModal, setShowBranchModal] = useState(false);
  const [isBranchCreating, setIsBranchCreating] = useState(false);
  const [statusMessage, setStatusMessage] = useState('');
  const [error, setError] = useState('');

  // ブランチを作成するクリックハンドラ
  const handleCreateBranch = async () => {
    if (isBranchCreating) return;
    setError('');
    setIsBranchCreating(true);

    try {
      // タイムスタンプの作成
      const timestamp = Math.floor(Date.now() / 1000);
      const email = user?.email || 'anonymous';
      const branchName = `feature/${email}_${timestamp}`;

      const response = await apiClient.post(API_CONFIG.ENDPOINTS.GIT.CREATE_BRANCH, {
        branchName,
        fromBranch: 'main',
      });

      if (response && response.success) {
        setStatusMessage('作業ブランチが作成されました。編集を開始できます。');
        setShowBranchModal(false);
      } else {
        setError('ブランチの作成に失敗しました');
      }
    } catch (err) {
      console.error('ブランチ作成エラー:', err);
      setError(err instanceof Error ? err.message : '予期しないエラーが発生しました');
    } finally {
      setIsBranchCreating(false);
    }
  };

  // 編集を開始するクリックハンドラ
  const handleStartEditing = async () => {
    setError('');

    try {
      // 現在のブランチの変更状態を確認
      const response = await apiClient.get(API_CONFIG.ENDPOINTS.GIT.CHECK_DIFF);

      if (response && response.hasDiff) {
        // 変更がある場合は直接編集開始
        setStatusMessage('既に差分があります。編集を続けてください。');
      } else {
        // 変更がない場合はモーダルを表示
        setShowBranchModal(true);
      }
    } catch (err) {
      console.error('Git状態確認エラー:', err);
      setError('Git状態の確認に失敗しました');
    }
  };

  return (
    <header className={clsx('hero hero--primary', styles.heroBanner)}>
      <div className="container">
        <Heading as="h1" className="hero__title">
          {siteConfig.title}
        </Heading>
        <p className="hero__subtitle">{siteConfig.tagline}</p>
        <div className={styles.buttons}>
          <Link className="button button--secondary button--lg mr-4" to="/docs/intro">
            Docusaurus Tutorial - 5min ⏱️
          </Link>

          {/* 編集開始ボタン */}
          <button className="button button--primary button--lg" onClick={handleStartEditing}>
            編集を開始
          </button>
        </div>

        {statusMessage && <div className="alert alert--success mt-4">{statusMessage}</div>}

        {error && <div className="alert alert--danger mt-4">{error}</div>}

        {/* ブランチ作成確認モーダル */}
        {showBranchModal && (
          <div
            className="modal-backdrop fade show"
            style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}
          >
            <div className="modal-dialog">
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">差分を作成しますか？</h5>
                  <button
                    type="button"
                    className="close"
                    onClick={() => setShowBranchModal(false)}
                    disabled={isBranchCreating}
                  >
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
                <div className="modal-body">
                  <p>現在のブランチには変更がありません。新しい作業ブランチを作成しますか？</p>
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    className="button button--secondary"
                    onClick={() => setShowBranchModal(false)}
                    disabled={isBranchCreating}
                  >
                    キャンセル
                  </button>
                  <button
                    type="button"
                    className="button button--primary"
                    onClick={handleCreateBranch}
                    disabled={isBranchCreating}
                  >
                    {isBranchCreating ? '作成中...' : 'はい'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </header>
  );
}

export default function Home(): ReactNode {
  const { siteConfig } = useDocusaurusContext();
  return (
    <Layout
      title={`Hello from ${siteConfig.title}`}
      description="Description will go into a meta tag in <head />"
    >
      <HomepageHeader />
      <main>
        <HomepageFeatures />
      </main>
    </Layout>
  );
}
