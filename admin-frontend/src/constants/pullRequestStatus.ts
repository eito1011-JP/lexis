export const PULL_REQUEST_STATUS = {
  OPENED: 'opened',
  MERGED: 'merged',
  CLOSED: 'closed',
  CONFLICT: 'conflict',
} as const;

export type PullRequestStatus = typeof PULL_REQUEST_STATUS[keyof typeof PULL_REQUEST_STATUS]; 