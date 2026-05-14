import * as Select from "@radix-ui/react-select";
import "./CustomSelect.css";

type Option = { value: string; label: string; image?: string };
type OptionGroup = { label: string; options: Option[] };

type Props = {
  value: string;
  onChange: (value: string) => void;
  options?: Option[];
  groups?: OptionGroup[];
  placeholder?: string;
  disabled?: boolean;
  id?: string;
  className?: string;
};

function findOption(value: string, options?: Option[], groups?: OptionGroup[]): Option | undefined {
  if (options) return options.find(o => o.value === value);
  if (groups) {
    for (const g of groups) {
      const found = g.options.find(o => o.value === value);
      if (found) return found;
    }
  }
}

export default function CustomSelect({
  value,
  onChange,
  options,
  groups,
  placeholder = "Select…",
  disabled = false,
  id,
  className = "",
}: Props) {
  const selectedOption = value ? findOption(value, options, groups) : undefined;

  return (
    <Select.Root value={value} onValueChange={onChange} disabled={disabled}>
      <Select.Trigger
        id={id}
        className={`cs-trigger${className ? ` ${className}` : ""}`}
        aria-label={placeholder}
      >
        <span className="cs-trigger-value">
          {selectedOption?.image && (
            <img src={selectedOption.image} className="cs-option-img" alt="" aria-hidden />
          )}
          <Select.Value placeholder={<span className="cs-placeholder">{placeholder}</span>} />
        </span>
        <Select.Icon className="cs-icon">
          <svg width="12" height="8" viewBox="0 0 12 8" fill="none" aria-hidden>
            <path d="M1 1l5 5 5-5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </Select.Icon>
      </Select.Trigger>

      <Select.Portal>
        <Select.Content className="cs-content" position="popper" sideOffset={4}>
          <Select.Viewport className="cs-viewport">
            {options &&
              options.map((opt) => (
                <Select.Item key={opt.value} value={opt.value} className="cs-item">
                  {opt.image && <img src={opt.image} className="cs-option-img" alt="" aria-hidden />}
                  <Select.ItemText>{opt.label}</Select.ItemText>
                </Select.Item>
              ))}

            {groups &&
              groups.map((g) => (
                <Select.Group key={g.label}>
                  <Select.Label className="cs-group-label">{g.label}</Select.Label>
                  {g.options.map((opt) => (
                    <Select.Item key={opt.value} value={opt.value} className="cs-item">
                      {opt.image && <img src={opt.image} className="cs-option-img" alt="" aria-hidden />}
                      <Select.ItemText>{opt.label}</Select.ItemText>
                    </Select.Item>
                  ))}
                </Select.Group>
              ))}
          </Select.Viewport>
        </Select.Content>
      </Select.Portal>
    </Select.Root>
  );
}
