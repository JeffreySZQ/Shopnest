import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'

const money = (amount) => `RM ${Number(amount || 0).toFixed(2)}`

/** Build a receipt from the saved order snapshot, never live product prices. */
export function downloadReceipt(order) {
  if (!order || order.paymentStatus !== 'paid') {
    throw new Error('A receipt is available only for a paid order.')
  }

  const doc = new jsPDF({ unit: 'mm', format: 'a4' })
  const margin = 16
  const pageWidth = doc.internal.pageSize.getWidth()
  const paidDate = new Date(order.createdAt).toLocaleString('en-MY', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Kuala_Lumpur'
  })

  doc.setFillColor(20, 36, 59)
  doc.rect(0, 0, pageWidth, 40, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(22)
  doc.text('ShopNest', margin, 20)
  doc.setFontSize(10)
  doc.setFont('helvetica', 'normal')
  doc.text('PURCHASE RECEIPT - DEMONSTRATION', margin, 29)

  doc.setTextColor(20, 36, 59)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(15)
  doc.text('Receipt details', margin, 52)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  doc.text(`Order ID: ${order.id}`, margin, 62)
  doc.text(`Purchase date: ${paidDate}`, margin, 69)
  doc.text(`Payment status: ${order.paymentStatus.toUpperCase()} (simulated)`, margin, 76)
  doc.text(`Fulfillment: ${order.fulfillmentStatus}`, margin, 83)

  doc.setFont('helvetica', 'bold')
  doc.text('Billed to', 110, 62)
  doc.setFont('helvetica', 'normal')
  const recipientLines = doc.splitTextToSize(`${order.customer || 'Customer'}\n${order.email || ''}`, 83)
  doc.text(recipientLines, 110, 69)

  const rows = (order.items || []).map(item => [
    String(item.name),
    String(item.quantity),
    money(item.price),
    money(item.price * item.quantity)
  ])

  autoTable(doc, {
    startY: 99,
    head: [['Product', 'Qty', 'Unit price', 'Amount']],
    body: rows,
    theme: 'striped',
    headStyles: { fillColor: [35, 79, 156], textColor: [255, 255, 255] },
    styles: { font: 'helvetica', fontSize: 9, cellPadding: 3.5 },
    columnStyles: { 1: { halign: 'center', cellWidth: 17 }, 2: { halign: 'right', cellWidth: 33 }, 3: { halign: 'right', cellWidth: 33 } },
    margin: { left: margin, right: margin }
  })

  let y = (doc.lastAutoTable?.finalY || 110) + 12
  const totals = [
    ['Subtotal', money(order.subtotal)],
    ['Shipping', money(order.shipping)],
    ['TOTAL PAID (DEMO)', money(order.total)]
  ]
  if (y + 38 > doc.internal.pageSize.getHeight() - 28) {
    doc.addPage()
    y = 24
  }
  totals.forEach(([label, amount], index) => {
    doc.setFont('helvetica', index === 2 ? 'bold' : 'normal')
    doc.setFontSize(index === 2 ? 12 : 10)
    doc.text(label, margin, y)
    doc.text(amount, pageWidth - margin, y, { align: 'right' })
    y += index === 2 ? 12 : 8
  })

  const footY = doc.internal.pageSize.getHeight() - 16
  doc.setDrawColor(220, 227, 235)
  doc.line(margin, footY - 7, pageWidth - margin, footY - 7)
  doc.setTextColor(73, 90, 112)
  doc.setFontSize(8)
  doc.text('Generated automatically from the completed ShopNest demo order.', margin, footY)
  doc.text('NOT A REAL TAX INVOICE OR PAYMENT CONFIRMATION.', margin, footY + 4)
  doc.save(`ShopNest-Receipt-${String(order.id).replace(/[^a-zA-Z0-9-]/g, '')}.pdf`)
}
