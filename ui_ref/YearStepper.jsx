import React from "react";

const ChevronLeft = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M15 6l-6 6 6 6" />
  </svg>
);
const ChevronRight = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M9 6l6 6-6 6" />
  </svg>
);

/**
 * YearStepper - "< 2026 >" control used above charts.
 */
export default function YearStepper({ year, onPrev, onNext }) {
  return (
    <div className="flex items-center border border-line rounded-control overflow-hidden text-sm">
      <button
        onClick={onPrev}
        className="w-8 h-8 flex items-center justify-center text-ink-500 hover:bg-gray-50 border-r border-line"
      >
        <span className="w-4 h-4 block"><ChevronLeft /></span>
      </button>
      <span className="px-4 h-8 flex items-center font-medium text-ink-700 min-w-[64px] justify-center">
        {year}
      </span>
      <button
        onClick={onNext}
        className="w-8 h-8 flex items-center justify-center text-ink-500 hover:bg-gray-50 border-l border-line"
      >
        <span className="w-4 h-4 block"><ChevronRight /></span>
      </button>
    </div>
  );
}
