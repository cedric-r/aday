interface Props {
  value: string;
  onChange: (tz: string) => void;
  id?: string;
  required?: boolean;
}

type TzGroups = Record<string, string[]>;

const buildGroups = (): TzGroups => {
  const zones = Intl.supportedValuesOf('timeZone');
  const groups: TzGroups = {};

  for (const tz of zones) {
    const slashIdx = tz.indexOf('/');
    const continent = slashIdx === -1 ? 'Other' : tz.slice(0, slashIdx);
    if (!groups[continent]) groups[continent] = [];
    groups[continent].push(tz);
  }

  return groups;
};

const TZ_GROUPS = buildGroups();

export const TimezoneSelect = ({ value, onChange, id, required }: Props) => (
  <select
    id={id}
    value={value}
    required={required}
    onChange={(e) => onChange(e.target.value)}
  >
    {Object.entries(TZ_GROUPS).map(([continent, zones]) => (
      <optgroup key={continent} label={continent}>
        {zones.map((tz) => (
          <option key={tz} value={tz}>
            {tz.replace(/_/g, ' ')}
          </option>
        ))}
      </optgroup>
    ))}
  </select>
);
