// 差分データの型定義
export type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
};

export type DiffFieldInfo = {
  status: 'added' | 'deleted' | 'modified' | 'unchanged';
  current: any;
  original: any;
};

export type DiffDataInfo = {
  id: number;
  type: 'document' | 'category';
  operation: 'created' | 'updated' | 'deleted';
  changed_fields: Record<string, DiffFieldInfo>;
};

// タブ定義
export type TabType = 'activity' | 'changes';

export const TABS = [
  { id: 'activity' as TabType, label: 'アクティビティ', icon: '💬' },
  { id: 'changes' as TabType, label: '変更内容', icon: '📝' },
] as const;

// 確認アクションの型定義
export type ConfirmationAction =
  | 'create_correction_request'
  | 're_edit_proposal'
  | 'approve_changes';

export const CONFIRMATION_ACTIONS = [
  {
    value: 'create_correction_request' as ConfirmationAction,
    label: '修正リクエストを作成',
  },
  {
    value: 're_edit_proposal' as ConfirmationAction,
    label: '再編集する',
  },
  {
    value: 'approve_changes' as ConfirmationAction,
    label: '承認する',
  },
];
