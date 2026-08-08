import React from "react";

const CHIP_TONES = {
  blue: "bg-chip-blue text-blue-600",
  indigo: "bg-chip-indigo text-indigo-600",
  emerald: "bg-chip-emerald text-emerald-600",
  amber: "bg-chip-amber text-amber-600",
};

/**
 * StatCard - single metric tile used in the "Overview" row
 * (e.g. Projects / Total Fee / Transactions / Gross Amount).
 * Renders borderless when `divider` is false and used inside a
 * shared row (pass divider=false + wrap in a flex row with dividers
 * yourself, or just render each as its own bordered card).
 */
export default function StatCard({ icon, label, value, tone = "blue", className = "" }) {
  return (
    <div className={`flex items-center gap-4 p-5 ${className}`}>
      <div
        className={`w-11 h-11 rounded-lg flex items-center justify-center shrink-0 ${CHIP_TONES[tone]}`}
      >
        <span className="w-5 h-5">{icon}</span>
      </div>
      <div>
        <div className="text-sm text-ink-500">{label}</div>
        <div className="text-2xl font-bold text-ink-900 mt-0.5">{value}</div>
      </div>
    </div>
  );
}

/**
 * StatRow - lays out StatCards in a bordered white panel with
 * vertical dividers between items, matching the Overview strip.
 */
export function StatRow({ children }) {
  return (
    <div className="bg-surface border border-line rounded-card shadow-panel flex divide-x divide-line overflow-hidden">
      {React.Children.map(children, (child) => (
        <div className="flex-1">{child}</div>
      ))}
    </div>
  );
}
