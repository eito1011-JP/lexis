import React from 'react';

interface LineBreakProps {
  width?: number;
  height?: number;
}

export const LineBreak: React.FC<LineBreakProps> = ({ width = 16, height = 16 }) => {
  return (
    <svg
      width={width}
      height={height}
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M2 3h12M2 8h8M2 13h4M10 11l2 2 2-2"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
};
