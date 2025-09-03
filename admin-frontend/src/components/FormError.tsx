import React from "react";

type FormErrorProps = {
  className?: string;
  children: React.ReactNode;
};

export default function FormError({ className, children }: FormErrorProps) {
  return (
    <div className={`form-notification form--wrong text-[#DA3633] ${className}`}>
      {children}
    </div>
  );
}
