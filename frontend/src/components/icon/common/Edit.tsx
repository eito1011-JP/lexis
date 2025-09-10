import React from 'react';

interface EditProps {
  className?: string;
}

export const Edit: React.FC<EditProps> = ({ className = '' }) => {
  return (
    <svg
      width="12"
      height="12"
      viewBox="0 0 12 12"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <path
        d="M8.15141 2.1515C8.2621 2.03689 8.39451 1.94547 8.54092 1.88258C8.68732 1.81969 8.84479 1.78659 9.00412 1.7852C9.16346 1.78382 9.32147 1.81418 9.46895 1.87452C9.61643 1.93485 9.75041 2.02396 9.86308 2.13663C9.97575 2.2493 10.0649 2.38328 10.1252 2.53076C10.1855 2.67823 10.2159 2.83625 10.2145 2.99558C10.2131 3.15492 10.18 3.31238 10.1171 3.45879C10.0542 3.60519 9.96282 3.73761 9.8482 3.8483L9.37241 4.3241L7.67561 2.6273L8.15141 2.1515ZM6.8272 3.4757L1.7998 8.5031V10.1999H3.4966L8.52461 5.1725L6.8272 3.4757Z"
        stroke="white"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
};
