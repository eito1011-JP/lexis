import React from 'react';

interface PlusProps {
  className?: string;
}

export const Plus: React.FC<PlusProps> = ({ className = '' }) => {
  return (
    <svg
      width="8"
      height="9"
      viewBox="0 0 8 9"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <path
        d="M3.14418 8.40874V0.866122H5.05114V8.40874H3.14418ZM0.326349 5.59091V3.68395H7.86896V5.59091H0.326349Z"
        fill="white"
      />
    </svg>
  );
};
