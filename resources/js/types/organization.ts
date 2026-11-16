export type OrganizationRole = 'owner' | 'admin' | 'member';

export type Organization = {
    id: number;
    name: string;
    address: string;
    timezone: string;
    role: OrganizationRole;
    labs_count: number;
};

export type OrganizationSummary = {
    id: number;
    name: string;
    address: string;
    timezone: string;
    role: OrganizationRole | null;
};

export type OrganizationOption = {
    id: number;
    name: string;
};

export type OrganizationSwitch = {
    selected: OrganizationOption | null;
    options: OrganizationOption[];
};
