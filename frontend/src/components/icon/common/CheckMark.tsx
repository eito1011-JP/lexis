import React from 'react';

interface CheckMarkIconProps {
  className?: string;
  width?: number;
  height?: number;
}

export const CheckMark: React.FC<CheckMarkIconProps> = ({
  className = '',
  width = 15,
  height = 15,
}) => {
  return (
    <svg
      width={width}
      height={height}
      viewBox="0 0 15 15"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <path
        d="M14.125 3.9375C14.125 4.17738 14.0334 4.41738 13.8503 4.6002L6.35034 12.1002C6.16797 12.2842 5.92773 12.375 5.6875 12.375C5.44727 12.375 5.20762 12.2834 5.0248 12.1003L1.2748 8.35034C1.0917 8.16797 1 7.92773 1 7.6875C1 7.15195 1.43799 6.75 1.9375 6.75C2.17738 6.75 2.41738 6.84155 2.6002 7.02466L5.6875 10.1133L12.5254 3.27539C12.707 3.0917 12.9473 3 13.1875 3C13.6885 3 14.125 3.40137 14.125 3.9375Z"
        fill="currentColor"
      />
    </svg>
  );
};
