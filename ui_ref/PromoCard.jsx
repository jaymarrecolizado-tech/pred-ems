import React from "react";

/**
 * PromoCard - small floating white card with an icon chip, bold
 * title, and short description. Used inside HeroBanner (e.g. "Get Paid").
 */
export default function PromoCard({ icon, title, description, tone = "blue", className = "" }) {
  const chipTones = {
    blue: "bg-chip-blue text-blue-600",
    indigo: "bg-chip-indigo text-indigo-600",
    emerald: "bg-chip-emerald text-emerald-600",
    amber: "bg-chip-amber text-amber-600",
  };

  return (
    <div
      className={`bg-white rounded-card shadow-panel p-4 flex items-start gap-3 max-w-xs ${className}`}
    >
      <div
        className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${chipTones[tone]}`}
      >
        <span className="w-5 h-5">{icon}</span>
      </div>
      <div>
        <div className="font-bold text-ink-900">{title}</div>
        <div className="text-sm text-ink-500 mt-0.5">{description}</div>
      </div>
    </div>
  );
}
