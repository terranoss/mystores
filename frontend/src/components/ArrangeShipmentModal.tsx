import { useEffect, useState } from 'react';
import Spinner from './Spinner';
import {
  getShippingParameters,
  shipOrder,
  type Order,
  type PickupAddress,
  type DropoffBranch,
  type ShippingTimeSlot,
} from '../api/client';

interface Props {
  order: Order;
  onClose: () => void;
  onShipped: (updatedOrder: Order) => void;
}

type Mode = 'pickup' | 'dropoff' | 'non_integrated';

function isDefaultAddress(addr: PickupAddress): boolean {
  if (addr.default_address) return true;
  return (addr.address_flag ?? []).includes('default_address');
}

function normalizePickupAddresses(list: PickupAddress[] | undefined): PickupAddress[] {
  return (list ?? []).map((addr) => ({
    ...addr,
    default_address: isDefaultAddress(addr),
    time_slot_list: addr.time_slot_list ?? [],
    country: addr.country ?? addr.region ?? '',
  }));
}

export default function ArrangeShipmentModal({ order, onClose, onShipped }: Props) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  // Available modes from Shopee
  const [availableModes, setAvailableModes] = useState<Mode[]>([]);
  const [pickupAddresses, setPickupAddresses] = useState<PickupAddress[]>([]);
  const [dropoffBranches, setDropoffBranches] = useState<DropoffBranch[]>([]);
  const [pickupRequiredFields, setPickupRequiredFields] = useState<string[]>([]);
  const [dropoffRequiredFields, setDropoffRequiredFields] = useState<string[]>([]);

  // Selection state
  const [mode, setMode] = useState<Mode | null>(null);

  // Pickup selections
  const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);
  const [selectedTimeSlot, setSelectedTimeSlot] = useState<ShippingTimeSlot | null>(null);

  // Dropoff selections
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    getShippingParameters(order.id)
      .then((data) => {
        const infoNeeded = data?.info_needed ?? {};
        const modes: Mode[] = [];
        const addresses = normalizePickupAddresses(data?.pickup?.address_list);
        const branches = data?.dropoff?.branch_list ?? [];

        // info_needed.pickup/dropoff may be [] meaning "include the mode, no fields required"
        if (infoNeeded.pickup !== undefined) {
          modes.push('pickup');
          setPickupAddresses(addresses);
          setPickupRequiredFields(infoNeeded.pickup ?? []);
        } else {
          setPickupAddresses([]);
          setPickupRequiredFields([]);
        }
        if (infoNeeded.dropoff !== undefined) {
          modes.push('dropoff');
          setDropoffBranches(branches);
          setDropoffRequiredFields(infoNeeded.dropoff ?? []);
        } else {
          setDropoffBranches([]);
          setDropoffRequiredFields([]);
        }
        if (infoNeeded.non_integrated !== undefined) {
          modes.push('non_integrated');
        }

        setAvailableModes(modes);
        // Default to first available mode; prefer dropoff for simplicity
        const defaultMode = modes.includes('dropoff')
          ? 'dropoff'
          : modes.includes('pickup')
          ? 'pickup'
          : modes[0] ?? null;
        setMode(defaultMode);
      })
      .catch((e: any) => {
        setError(e?.response?.data?.message || e?.message || 'Failed to load shipping options.');
      })
      .finally(() => setLoading(false));
  }, [order.id]);

  // Auto-select default pickup address
  const selectedAddress = pickupAddresses.find((a) => a.address_id === selectedAddressId);
  useEffect(() => {
    if (mode === 'pickup' && !selectedAddressId && pickupAddresses.length > 0) {
      const def = pickupAddresses.find((a) => a.default_address) ?? pickupAddresses[0];
      setSelectedAddressId(def.address_id);
      setSelectedTimeSlot(def.time_slot_list?.[0] ?? null);
    }
  }, [mode, pickupAddresses, selectedAddressId]);

  // Auto-select default dropoff branch
  useEffect(() => {
    if (mode === 'dropoff' && !selectedBranchId && dropoffBranches.length > 0) {
      setSelectedBranchId(dropoffBranches[0].branch_id);
    }
  }, [mode, dropoffBranches, selectedBranchId]);

  const handleSubmit = async () => {
    if (!mode) return;
    setSubmitting(true);
    setError(null);
    try {
      let params: Parameters<typeof shipOrder>[1] = {};

      if (mode === 'pickup') {
        const needsAddress = pickupRequiredFields.includes('address_id') || pickupAddresses.length > 0;
        const needsSlot = pickupRequiredFields.includes('pickup_time_id');
        if (needsAddress && !selectedAddressId) {
          setError('Please select an address.');
          setSubmitting(false);
          return;
        }
        const slots = selectedAddress?.time_slot_list ?? [];
        if ((needsSlot || slots.length > 0) && slots.length > 0 && !selectedTimeSlot?.pickup_time_id) {
          setError('Please select an address and time slot.');
          setSubmitting(false);
          return;
        }
        const pickup: { address_id?: number; pickup_time_id?: string } = {};
        if (selectedAddressId) pickup.address_id = selectedAddressId;
        if (selectedTimeSlot?.pickup_time_id) {
          pickup.pickup_time_id = selectedTimeSlot.pickup_time_id;
        }
        params = { pickup };
      } else if (mode === 'dropoff') {
        const needsBranch = dropoffRequiredFields.includes('branch_id') || dropoffBranches.length > 0;
        if (needsBranch && !selectedBranchId) {
          setError('Please select a dropoff branch.');
          setSubmitting(false);
          return;
        }
        params = {
          dropoff: selectedBranchId ? { branch_id: selectedBranchId } : {},
        };
      } else {
        params = { non_integrated: {} };
      }

      const updatedOrder = await shipOrder(order.id, params);
      setSuccess(true);
      setTimeout(() => {
        onShipped(updatedOrder);
        onClose();
      }, 1200);
    } catch (e: any) {
      setError(e?.response?.data?.message || e?.message || 'Failed to arrange shipment.');
    } finally {
      setSubmitting(false);
    }
  };

  const MODE_LABELS: Record<Mode, string> = {
    pickup:         '📦 Courier Pickup',
    dropoff:        '🏬 Drop Off at Branch',
    non_integrated: '✏️ Self-Arrange (Non-integrated)',
  };

  const selectedSlots = selectedAddress?.time_slot_list ?? [];

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="fixed inset-0 bg-ink-950/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-xl border border-ink-100 w-full max-w-lg max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="px-6 py-5 border-b border-ink-100 flex items-center justify-between shrink-0">
          <div>
            <h3 className="font-heading text-lg font-semibold text-ink-950">Arrange Shipment</h3>
            <p className="text-xs text-ink-400 font-mono mt-0.5">{order.order_sn}</p>
          </div>
          <button onClick={onClose} className="text-ink-400 hover:text-ink-700 transition text-xl leading-none">
            ✕
          </button>
        </div>

        {/* Body */}
        <div className="overflow-y-auto flex-1 p-6 space-y-5">
          {loading ? (
            <div className="flex items-center justify-center py-12 gap-2 text-ink-400 text-sm">
              <Spinner /> Loading shipping options…
            </div>
          ) : error && availableModes.length === 0 ? (
            <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>
          ) : availableModes.length === 0 ? (
            <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
              No shipping methods available for this order.
            </div>
          ) : (
            <>
              {error && (
                <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>
              )}
              {success && (
                <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-medium">
                  ✓ Shipment arranged! Updating order status…
                </div>
              )}

              {/* Mode Selector */}
              {availableModes.length > 1 && (
                <div>
                  <p className="text-sm font-semibold text-ink-700 mb-2">Shipping Method</p>
                  <div className="flex flex-wrap gap-2">
                    {availableModes.map((m) => (
                      <button
                        key={m}
                        onClick={() => setMode(m)}
                        className={`px-3 py-2 rounded-xl text-sm font-medium border transition ${
                          mode === m
                            ? 'bg-ink-950 text-white border-ink-950'
                            : 'border-ink-200 text-ink-700 hover:border-ink-400'
                        }`}
                      >
                        {MODE_LABELS[m]}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Pickup Options */}
              {mode === 'pickup' && (
                <>
                  {pickupAddresses.length === 0 ? (
                    <div className="p-4 rounded-xl bg-ink-50 border border-ink-100 text-ink-700 text-sm">
                      Shopee does not require a pickup address selection for this order. Confirm to arrange courier pickup.
                    </div>
                  ) : (
                  <div>
                    <p className="text-sm font-semibold text-ink-700 mb-2">Pickup Address</p>
                    <div className="space-y-2">
                      {pickupAddresses.map((addr) => (
                        <label
                          key={addr.address_id}
                          className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition ${
                            selectedAddressId === addr.address_id
                              ? 'border-ink-950 bg-ink-50'
                              : 'border-ink-200 hover:border-ink-300'
                          }`}
                        >
                          <input
                            type="radio"
                            name="pickup_address"
                            value={addr.address_id}
                            checked={selectedAddressId === addr.address_id}
                            onChange={() => {
                              setSelectedAddressId(addr.address_id);
                              setSelectedTimeSlot(addr.time_slot_list?.[0] ?? null);
                            }}
                            className="mt-0.5 accent-ink-950"
                          />
                          <span className="text-sm text-ink-900 leading-snug">
                            {addr.address}
                            {addr.city ? `, ${addr.city}` : ''}
                            {addr.state ? `, ${addr.state}` : ''}
                            {addr.zipcode ? ` ${addr.zipcode}` : ''}
                            {addr.default_address && (
                              <span className="ml-2 text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Default</span>
                            )}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>
                  )}

                  {selectedSlots.length > 0 && (
                    <div>
                      <p className="text-sm font-semibold text-ink-700 mb-2">Pickup Time Slot</p>
                      <div className="grid grid-cols-2 gap-2">
                        {selectedSlots.map((slot) => (
                          <label
                            key={slot.pickup_time_id}
                            className={`flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition text-sm ${
                              selectedTimeSlot?.pickup_time_id === slot.pickup_time_id
                                ? 'border-ink-950 bg-ink-50'
                                : 'border-ink-200 hover:border-ink-300'
                            }`}
                          >
                            <input
                              type="radio"
                              name="pickup_slot"
                              checked={selectedTimeSlot?.pickup_time_id === slot.pickup_time_id}
                              onChange={() => setSelectedTimeSlot(slot)}
                              className="accent-ink-950"
                            />
                            <span className="text-ink-800 leading-tight">
                              <span className="font-medium block">{slot.date}</span>
                              <span className="text-xs text-ink-500">{slot.time_text}</span>
                            </span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}
                </>
              )}

              {/* Dropoff Options */}
              {mode === 'dropoff' && (
                <div>
                  {dropoffBranches.length === 0 ? (
                    <div className="p-4 rounded-xl bg-ink-50 border border-ink-100 text-ink-700 text-sm">
                      Shopee does not require a branch selection for this order. Confirm to arrange drop-off.
                    </div>
                  ) : (
                    <>
                      <p className="text-sm font-semibold text-ink-700 mb-2">Dropoff Branch</p>
                      <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
                        {dropoffBranches.map((branch) => (
                          <label
                            key={branch.branch_id}
                            className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition ${
                              selectedBranchId === branch.branch_id
                                ? 'border-ink-950 bg-ink-50'
                                : 'border-ink-200 hover:border-ink-300'
                            }`}
                          >
                            <input
                              type="radio"
                              name="dropoff_branch"
                              value={branch.branch_id}
                              checked={selectedBranchId === branch.branch_id}
                              onChange={() => setSelectedBranchId(branch.branch_id)}
                              className="mt-0.5 accent-ink-950"
                            />
                            <span className="text-sm text-ink-900 leading-snug">
                              <span className="font-medium block">{branch.branch_name}</span>
                              <span className="text-ink-500">{branch.address}</span>
                            </span>
                          </label>
                        ))}
                      </div>
                    </>
                  )}
                </div>
              )}

              {/* Non-integrated */}
              {mode === 'non_integrated' && (
                <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                  <p className="font-semibold mb-1">Self-Arrange Logistics</p>
                  <p>You will arrange your own delivery. The order status will be updated to Processed once confirmed.</p>
                </div>
              )}
            </>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-ink-100 flex gap-3 shrink-0">
          <button
            onClick={onClose}
            disabled={submitting}
            className="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-ink-200 text-ink-700 hover:bg-ink-50 transition disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={loading || submitting || success || !mode}
            className="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 transition active:scale-[0.97] inline-flex items-center justify-center gap-2"
          >
            {submitting && <Spinner />}
            {success ? 'Arranged ✓' : submitting ? 'Arranging…' : 'Confirm Shipment'}
          </button>
        </div>
      </div>
    </div>
  );
}
