import React from 'react';

interface ArrowDownProps {
  className?: string;
}

/**
 * 下向き矢印アイコンコンポーネント
 */
export const ArrowDown: React.FC<ArrowDownProps> = ({ className = '' }) => {
  return (
    <svg 
      width="19" 
      height="19" 
      viewBox="0 0 19 19" 
      fill="none" 
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <path 
        d="M4.75 7.125L9.5 11.875L14.25 7.125" 
        stroke="#B1B1B1" 
        strokeWidth="2" 
        strokeLinecap="round" 
        strokeLinejoin="round"
      />
    </svg>
  );
};
