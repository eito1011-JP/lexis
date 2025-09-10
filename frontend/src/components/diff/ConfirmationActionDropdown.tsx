import React, { useState } from 'react';
import type { ConfirmationAction } from '@/types/diff';
import { CONFIRMATION_ACTIONS } from '@/types/diff';

interface ConfirmationActionDropdownProps {
  selectedAction: ConfirmationAction;
  onActionChange: (action: ConfirmationAction) => void;
  onConfirm: () => void;
}

export const ConfirmationActionDropdown: React.FC<ConfirmationActionDropdownProps> = ({
  selectedAction,
  onActionChange,
  onConfirm,
}) => {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center px-4 py-2 bg-gray-800 border border-gray-600 rounded-md text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <span>アクション</span>
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-64 bg-gray-800 border border-gray-600 rounded-md shadow-lg z-10">
          <div className="p-4">
            <div className="space-y-3">
              {CONFIRMATION_ACTIONS.map(action => (
                <label key={action.value} className="flex items-center cursor-pointer">
                  <input
                    type="radio"
                    name="confirmationAction"
                    value={action.value}
                    checked={selectedAction === action.value}
                    onChange={() => onActionChange(action.value)}
                    className="mr-3 text-blue-500 focus:ring-blue-500"
                  />
                  <span className="text-white text-sm">{action.label}</span>
                </label>
              ))}
            </div>
            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={() => {
                  onConfirm();
                  setIsOpen(false);
                }}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                確定する
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
