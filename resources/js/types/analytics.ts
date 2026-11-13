export interface Kpi {
    value: number;
    previous: number;
    /** rounded percentage vs the previous period, null when there is nothing to compare with */
    delta: number | null;
}

export interface BreakdownRow {
    name: string;
    count: number;
    total: number;
}

export interface DailyPoint {
    date: string;
    vehicles: number;
    collected: number;
    first_visits: number;
    repeat_visits: number;
}

export interface RecurringVehicle {
    vehicle_id: number;
    plate: string;
    customer_name: string | null;
    visits: number;
    total_visits: number;
    spent: number;
    last_visit: string;
}

export interface Analytics {
    range: number;
    from: string;
    to: string;
    kpis: { vehicles: Kpi; collected: Kpi; new_vehicles: Kpi; returning_vehicles: Kpi };
    daily: DailyPoint[];
    hourly: number[];
    by_type: BreakdownRow[];
    by_service: BreakdownRow[];
    recurring: RecurringVehicle[];
}
