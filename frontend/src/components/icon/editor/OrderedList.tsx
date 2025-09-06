import React from 'react';

interface OrderedListProps {
  width?: number;
  height?: number;
  className?: string;
}

export const OrderedList: React.FC<OrderedListProps> = ({
  width = 24,
  height = 24,
  className = '',
}) => {
  return (
    <svg
      width={width}
      height={height}
      className={className}
      viewBox="0 0 500 500"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path d="M20 120V50L0 60V35L20 25H45V120H20Z" fill="white" />
      <path d="M405 80H105V98H405V80Z" fill="white" />
      <path
        d="M0 260V240L30 210C40 200 45 192 45 185C45 178 40 173 32 173C25 173 16 178 8 185L0 170C10 160 22 155 35 155C55 155 70 167 70 185C70 200 60 213 45 225L25 240H70V260H0Z"
        fill="white"
      />
      <path d="M405 220H105V238H405V220Z" fill="white" />
      <path
        d="M35 400C17 400 5 395 0 380L15 365C20 375 25 380 35 380C43 380 48 375 48 368C48 360 43 355 30 355H20V335H30C40 335 45 330 45 323C45 316 40 312 33 312C26 312 20 316 15 325L0 310C10 298 20 295 35 295C55 295 65 305 65 322C65 332 60 340 50 345C62 350 70 360 70 370C70 387 55 400 35 400Z"
        fill="white"
      />
      <path d="M405 360H105V378H405V360Z" fill="white" />
    </svg>
  );
};
