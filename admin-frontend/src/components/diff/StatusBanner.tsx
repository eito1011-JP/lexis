import React from 'react';
import { formatDistanceToNow } from 'date-fns';
import ja from 'date-fns/locale/ja';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { Merge } from '@/components/icon/common/Merge';
import { Merged } from '@/components/icon/common/Merged';
import { Closed } from '@/components/icon/common/Closed';

interface StatusBannerProps {
  status: string;
  authorEmail: string;
  createdAt: string;
  conflict: boolean;
  title: string;
}

export const StatusBanner: React.FC<StatusBannerProps> = ({
  status,
  authorEmail,
  createdAt,
  conflict,
  title,
}) => {
  let button;
  switch (true) {
    case conflict:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#DA3633] focus:outline-none"
          disabled
        >
          <Closed className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">コンフリクト</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.MERGED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#3832A5] focus:outline-none"
          disabled
        >
          <Merged className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">反映済み</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.OPENED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#1B6E2A] focus:outline-none"
          disabled
        >
          <Merge className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">未対応</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.CLOSED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#DA3633] focus:outline-none"
          disabled
        >
          <Closed className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">取り下げ</span>
        </button>
      );
      break;
    default:
      button = null;
  }

  return (
    <div className={`mb-10 rounded-lg`}>
      {/* タイトル表示 */}
      <h1 className="text-3xl font-bold text-white mb-4">{title}</h1>
      <div className="flex items-center justify-start">
        {button}
        <span className="font-medium text-[#B1B1B1] ml-4">
          {authorEmail}さんが{' '}
          {formatDistanceToNow(new Date(createdAt), { addSuffix: true, locale: ja })}{' '}
          に変更を提出しました
        </span>
      </div>
    </div>
  );
};
