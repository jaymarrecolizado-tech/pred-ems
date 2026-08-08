import React from "react";

const TableIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="3" y="5" width="18" height="14" rx="1" />
    <path d="M3 10h18M9 5v14" />
  </svg>
);
const AreaIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M3 17l5-6 4 4 5-8 4 5" />
    <path d="M3 20h18" />
  </svg>
);
const BarIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M4 20V10M12 20V4M20 20v-7" />
  </svg>
);

const OPTIONS = [
  { key: "table", icon: TableIcon },
  { key: "area", icon: AreaIcon },
  { key: "bar", icon: BarIcon },
];

/**
 * ViewToggle - 3-button icon group for switching a panel's view
 * (table / area chart / bar chart). value: "table" | "area" | "bar"
 */
export default function ViewToggle({ value = "area", onChange }) {
  return (
    <div className="flex items-center gap-1.5">
      {OPTIONS.map(({ key, icon: Ico }) => {
        const active = value === key;
        return (
          <button
            key={key}
            onClick={() => onChange?.(key)}
            className={`w-8 h-8 flex items-center justify-center rounded-control border transition-colors ${
              active
                ? "bg-brand-600 border-brand-600 text-white"
                : "bg-white border-line text-ink-500 hover:bg-gray-50"
            }`}
          >
            <span className="w-4 h-4 block">
              <Ico />
            </span>
          </button>
        );
      })}
    </div>
  );
}
