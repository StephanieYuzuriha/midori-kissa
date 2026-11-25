// Shipping rates in euros (RF19), from GET /api/shipping
export interface ShippingRates {
  flatRate: number;
  freeFrom: number;
}

export type OrderStatus = 'pending_payment' | 'paid' | 'shipped' | 'delivered' | 'cancelled';

// Shipping data copied into the order
export interface ShippingData {
  name: string;
  address: string;
  postalCode: string;
  city: string;
  province: string;
  phone: string;
}

// Line of an order (OrderItem::toArray())
export interface OrderItem {
  productId: number;
  productName: string;
  productImage: string | null;
  quantity: number;
  unitPrice: number;
  subtotal: number;
}

// Order as returned by the API (Order::toArray()). The list of orders comes without lines
export interface Order {
  orderNumber: string;
  createdAt: string;
  status: OrderStatus;
  subtotal: number;
  shippingCost: number;
  total: number;
  shipping: ShippingData;
  items: OrderItem[];
}

// Data sent to create an order: only product ids and units, the server sets the prices (RS15)
export interface NewOrder {
  items: { productId: number; quantity: number }[];
  shipping: ShippingData;
}

// Text and colour of each status. The text is always shown, not only the colour (RI12)
export const ORDER_STATUS: Record<OrderStatus, { label: string; badge: string }> = {
  pending_payment: { label: 'Pendiente de pago', badge: 'text-bg-warning' },
  paid: { label: 'Pagado', badge: 'text-bg-success' },
  shipped: { label: 'Enviado', badge: 'text-bg-info' },
  delivered: { label: 'Entregado', badge: 'text-bg-dark' },
  cancelled: { label: 'Cancelado', badge: 'text-bg-secondary' },
};

// Shipping costs for a subtotal: free from the amount in the configuration (RF19)
export function shippingCostFor(subtotal: number, rates: ShippingRates): number {
  return subtotal >= rates.freeFrom ? 0 : rates.flatRate;
}
