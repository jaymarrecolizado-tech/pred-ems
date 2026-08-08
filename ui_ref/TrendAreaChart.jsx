import React from "react";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

/**
 * TrendAreaChart - blue gradient-fill area chart matching "By Transaction".
 * data: [{ label, value }]
 */
export default function TrendAreaChart({ data, height = 260 }) {
  const peak = Math.max(...data.map((d) => d.value));

  return (
    <div style={{ width: "100%", height }}>
      <ResponsiveContainer>
        <AreaChart data={data} margin={{ top: 24, right: 12, left: 0, bottom: 0 }}>
          <defs>
            <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#3B82F6" stopOpacity={0.35} />
              <stop offset="100%" stopColor="#3B82F6" stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#E5E7EB" />
          <XAxis dataKey="label" hide />
          <YAxis
            axisLine={false}
            tickLine={false}
            tick={{ fill: "#9CA3AF", fontSize: 12 }}
            width={28}
          />
          <Tooltip
            contentStyle={{
              borderRadius: 8,
              border: "1px solid #E5E7EB",
              fontSize: 12,
            }}
          />
          <Area
            type="monotone"
            dataKey="value"
            stroke="#3B82F6"
            strokeWidth={2}
            fill="url(#trendFill)"
            dot={(props) =>
              props.payload.value === peak ? (
                <g key={props.cx}>
                  <rect
                    x={props.cx - 12}
                    y={props.cy - 26}
                    width="24"
                    height="18"
                    rx="6"
                    fill="#3B82F6"
                  />
                  <text
                    x={props.cx}
                    y={props.cy - 13}
                    textAnchor="middle"
                    fontSize="11"
                    fontWeight="600"
                    fill="#fff"
                  >
                    {peak}
                  </text>
                </g>
              ) : (
                <g key={props.cx} />
              )
            }
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
