import React from "react";

/**
 * HeroBanner - soft lavender/blue gradient section with decorative
 * blurred pastel blobs, matching the dashboard top banner.
 * Pass heading/subtext as children (left side) and `card` for the
 * floating promo card slot (right side, e.g. "Get Paid").
 */
export default function HeroBanner({ children, card, className = "" }) {
  return (
    <div
      className={`relative overflow-hidden rounded-card bg-hero-gradient px-8 py-10 ${className}`}
    >
      {/* Decorative blobs */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -left-10 top-1/2 -translate-y-1/2 w-64 h-64 rounded-full bg-blue-200/50 blur-2xl" />
        <div className="absolute left-24 -top-10 w-40 h-40 rounded-full bg-amber-200/50 blur-2xl" />
        <div className="absolute left-40 top-16 w-32 h-32 rounded-full bg-rose-200/40 blur-2xl" />
      </div>

      <div className="relative flex items-start justify-between gap-8 flex-wrap">
        <div className="max-w-xl">{children}</div>
        {card && <div className="shrink-0 w-full sm:w-auto">{card}</div>}
      </div>
    </div>
  );
}
