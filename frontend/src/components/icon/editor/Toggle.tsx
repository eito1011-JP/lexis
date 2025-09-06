import React from 'react';

type ToggleProps = {
  className?: string;
  width?: number;
  height?: number;
};

export const Toggle: React.FC<ToggleProps> = ({ className = '', width, height }) => {
  return (
    <svg
      width={width}
      height={height}
      className={className}
      viewBox="0 0 6 5"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path d="M3 5L0.401924 0.5H5.59808L3 5Z" fill="#D9D9D9" />
    </svg>
  );
};

export default Toggle;
