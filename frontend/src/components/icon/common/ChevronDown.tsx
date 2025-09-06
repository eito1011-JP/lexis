import React from 'react';

interface ChevronDownIconProps {
  className?: string;
  width?: number;
  height?: number;
}

export const ChevronDown: React.FC<ChevronDownIconProps> = ({
  className = '',
  width = 8,
  height = 6,
}) => {
  return (
    <svg
      width={width}
      height={height}
      viewBox="0 0 8 6"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <path d="M4 6L0.535898 6.52533e-07L7.4641 4.68497e-08L4 6Z" fill="currentColor" />
    </svg>
  );
};
