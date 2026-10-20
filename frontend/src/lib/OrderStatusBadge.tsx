import {
  NEUTRAL_PRESENTATION,
  isCanonicalStatus,
  statusPresentation,
} from './orderStatus';

interface Props {
  /** The marketplace's own spelling, shown when it has no canonical mapping. */
  status?: string | null;
  /** The platform-independent state emitted by the backend. */
  canonical?: string | null;
}

export default function OrderStatusBadge({ status, canonical }: Props) {
  const canonicalStatus = isCanonicalStatus(canonical) ? canonical : null;
  const { label, tone, dot } = canonicalStatus
    ? statusPresentation(canonicalStatus)
    : NEUTRAL_PRESENTATION;
  const text = canonicalStatus ? label : (status ?? '');

  return (
    <span
      className={`inline-flex max-w-full items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border whitespace-normal text-left leading-snug ${tone}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />
      {text}
    </span>
  );
}
