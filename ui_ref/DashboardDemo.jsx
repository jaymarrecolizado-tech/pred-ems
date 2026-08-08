import React, { useState } from "react";
import Sidebar from "./components/Sidebar";
import TopBar from "./components/TopBar";
import Card from "./components/Card";
import Avatar from "./components/Avatar";
import HeroBanner from "./components/HeroBanner";
import PromoCard from "./components/PromoCard";
import { StatRow } from "./components/StatCard";
import StatCard from "./components/StatCard";
import YearStepper from "./components/YearStepper";
import ViewToggle from "./components/ViewToggle";
import TrendAreaChart from "./components/TrendAreaChart";

const Icon = ({ path }) => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d={path} />
  </svg>
);

const icons = {
  dashboard: "M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z",
  transactions: "M3 10h18M7 15h.01M11 15h4",
  projects: "M3 7h18v13H3zM3 7l3-4h12l3 4",
  reports: "M4 19h16M4 15l4-4 4 4 6-6",
  settings: "M12 8a4 4 0 100 8 4 4 0 000-8z",
  fee: "M20 7H4a2 2 0 00-2 2v9a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM2 10h20M6 4h12l2 3H4l2-3z",
  wallet: "M3 7h13a3 3 0 013 3v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM17 12h3",
  eye: "M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7zM12 15a3 3 0 100-6 3 3 0 000 6z",
};

const chartData = [
  { label: "Jan", value: 11 },
  { label: "Feb", value: 9 },
  { label: "Mar", value: 7 },
  { label: "Apr", value: 5 },
  { label: "May", value: 4 },
  { label: "Jun", value: 3 },
];

export default function DashboardDemo() {
  const [mode, setMode] = useState("live");
  const [view, setView] = useState("area");
  const [year, setYear] = useState(2026);

  const navItems = [
    { key: "dashboard", label: "Dashboard", icon: <Icon path={icons.dashboard} />, active: true },
    { key: "transactions", label: "Transactions", icon: <Icon path={icons.transactions} /> },
    { key: "projects", label: "Projects", icon: <Icon path={icons.projects} /> },
    { key: "reports", label: "Reports", icon: <Icon path={icons.reports} /> },
  ];
  const settingsItems = [
    { key: "settings", label: "Settings", icon: <Icon path={icons.settings} /> },
  ];

  return (
    <div className="flex min-h-screen bg-canvas font-sans">
      <Sidebar
        navItems={navItems}
        settingsItems={settingsItems}
        mode={mode}
        onModeChange={setMode}
      />

      <div className="flex-1 flex flex-col">
        <TopBar
          title="Dashboard"
          right={
            <>
              <div className="w-48 h-9 rounded-control border border-line" />
              <Avatar name="TR" />
            </>
          }
        />

        <main className="p-8 flex flex-col gap-6">
          {/* Hero */}
          <HeroBanner
            card={
              <PromoCard
                icon={<Icon path={icons.eye} />}
                title="Get Paid"
                description="Accept payments across multiple platforms: Messenger, Viber, SMS, email, and more."
                tone="emerald"
              />
            }
          >
            <h2 className="text-3xl font-extrabold text-ink-900 leading-tight">
              Collect payments anywhere, anytime
            </h2>
            <p className="text-ink-700 mt-3">
              Our comprehensive payment options include bank transfers, credit
              and debit cards, retail locations, installment plans, and
              e-Wallets, all conveniently accessible through a single
              integration.
            </p>
          </HeroBanner>

          {/* Overview */}
          <div>
            <div className="text-xs font-semibold tracking-wide2 text-ink-400 mb-2">
              OVERVIEW
            </div>
            <StatRow>
              <StatCard icon={<Icon path={icons.projects} />} label="Projects" value="3" tone="blue" />
              <StatCard icon={<Icon path={icons.fee} />} label="Total Fee" value="0.00" tone="indigo" />
              <StatCard icon={<Icon path={icons.reports} />} label="Transactions" value="12" tone="blue" />
              <StatCard icon={<Icon path={icons.wallet} />} label="Gross Amount" value="14.00" tone="indigo" />
            </StatRow>
          </div>

          {/* Charts row */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2">
              <Card
                title="By Transaction"
                actions={
                  <>
                    <YearStepper
                      year={year}
                      onPrev={() => setYear((y) => y - 1)}
                      onNext={() => setYear((y) => y + 1)}
                    />
                    <ViewToggle value={view} onChange={setView} />
                  </>
                }
              >
                <div className="p-6">
                  <TrendAreaChart data={chartData} />
                </div>
              </Card>
            </div>

            <Card
              title="Top Projects"
              actions={<ViewToggle value="bar" onChange={() => {}} />}
            >
              <div className="p-6 text-sm text-ink-400">No data yet.</div>
            </Card>
          </div>
        </main>
      </div>
    </div>
  );
}
