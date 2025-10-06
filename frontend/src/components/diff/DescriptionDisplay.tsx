import React from 'react';

interface DescriptionDisplayProps {
  description: string | null | undefined;
}

/**
 * プルリクエストの説明を表示するためのコンポーネント
 */
export const DescriptionDisplay: React.FC<DescriptionDisplayProps> = ({
  description,
}) => {
  return (
    <div className="text-white text-base leading-relaxed ml-[-1rem]">
      {description || 'この変更提案には説明がありません。'}
    </div>
  );
};
