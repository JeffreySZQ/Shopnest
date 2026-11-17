import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'


// Create a function to format money into Malaysian Ringgit.
const money = (amount) => `RM ${Number(amount || 0).toFixed(2)}`


// Export the function so other Vue files can use it.
// order contains the customer's saved purchase information.
export function downloadReceipt(order) {

  if (!order || order.paymentStatus !== 'paid') {
    throw new Error('A receipt is available only for a paid order.')
  }

  // Create a new pdf page 
  
  const doc = new jsPDF({ unit: 'mm', format: 'a4' })
  const margin = 16
  const pageWidth = doc.internal.pageSize.getWidth()
  const paidDate = new Date(order.createdAt).toLocaleString('en-MY', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Kuala_Lumpur'
  })

  // DESIGN THE SHOPNEST RECEIPT HEADER
  
  doc.setFillColor(20, 36, 59)
  doc.rect(0, 0, pageWidth, 40, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(22)
  doc.text('ShopNest', margin, 20)
  doc.setFontSize(10)
  doc.setFont('helvetica', 'normal')
  doc.text('PURCHASE RECEIPT - DEMONSTRATION', margin, 29)


  // ADD THE ORDER DETAILS

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


  //ADD THE CUSTOMER INFORMATION

  doc.setFont('helvetica', 'bold')
  doc.text('Billed to', 110, 62)
  doc.setFont('helvetica', 'normal')
  const recipientLines = doc.splitTextToSize(`${order.customer || 'Customer'}\n${order.email || ''}`, 83)
  doc.text(recipientLines, 110, 69)


  // PREPARE THE PURCHASED PRODUCT INFORMATION

  // Get all the products from the saved order.
  // map() goes through each purchased product one by one.
  const rows = (order.items || []).map(item => [
    String(item.name),
    String(item.quantity),
    money(item.price),
    money(item.price * item.quantity)

  ])


  // GENERATE THE PRODUCT TABLE INSIDE THE PDF

  autoTable(doc, {

    startY: 99,
    head: [['Product', 'Qty', 'Unit price', 'Amount']],
    body: rows,
    theme: 'striped',
    headStyles: {
      fillColor: [35, 79, 156],
      textColor: [255, 255, 255]
    },

    // Set the table font, text size and cell spacing.
    styles: {
      font: 'helvetica',
      fontSize: 9,
      cellPadding: 3.5
    },

    // Control the alignment and width of each table column.
    columnStyles: {

      // Centre the quantity and set its width to 17 mm.
      1: { halign: 'center', cellWidth: 17 },

      // Align the unit price to the right.
      2: { halign: 'right', cellWidth: 33 },

      // Align the amount to the right.
      3: { halign: 'right', cellWidth: 33 }

    },

    margin: { left: margin, right: margin }

  })


  // PREPARE THE ORDER TOTALS

  // Find where the product table ends and finalY gives the bottom position of the table.
  let y = (doc.lastAutoTable?.finalY || 110) + 12

  // Create an array containing the order totals.
  const totals = [

    // Get the subtotal from the saved order.
    ['Subtotal', money(order.subtotal)],
    ['Shipping', money(order.shipping)],
    ['TOTAL PAID (DEMO)', money(order.total)]

  ]


  //CHECK THE REMAINING PAGE SPACE

  // Check whether there is enough space to display the totals and get the total height of the PDF page.If there is not enough space, create another page.
  if (y + 38 > doc.internal.pageSize.getHeight() - 28) {
    doc.addPage()
    y = 24
  }


  // DISPLAY THE SUBTOTAL, SHIPPING AND TOTAL

  // Go through each total in the totals array, label represents the name of the total.
  totals.forEach(([label, amount], index) => {

    // If index is 2, use bold font for the final total.
    // Otherwise, use normal font.
    doc.setFont('helvetica', index === 2 ? 'bold' : 'normal')

    // If index is 2, use font size 12.
    // Otherwise, use font size 10.
    doc.setFontSize(index === 2 ? 12 : 10)

    // Display the total name on the left side of the PDF.
    doc.text(label, margin, y)

    // Display the price on the right side of the PDF.
    // align: 'right' aligns the price to the right.
    doc.text(amount, pageWidth - margin, y, { align: 'right' })
    y += index === 2 ? 12 : 8

  })


  // ADD THE FOOTER AND DISCLAIMER

  // Get the height of the PDF page and leave 16 mm at the bottom.
  const footY = doc.internal.pageSize.getHeight() - 16
  doc.setDrawColor(220, 227, 235)
  doc.line(margin, footY - 7, pageWidth - margin, footY - 7)
  doc.setTextColor(73, 90, 112)
  doc.setFontSize(8)
  doc.text('Generated automatically from the completed ShopNest demo order.', margin, footY)
  doc.text('NOT A REAL TAX INVOICE OR PAYMENT CONFIRMATION.', margin, footY + 4)


  // DOWNLOAD THE GENERATED PDF

  // Convert the order ID into text, remove special characters from the filename, use doc.save() to generate and download the PDF.
  doc.save(`ShopNest-Receipt-${String(order.id).replace(/[^a-zA-Z0-9-]/g, '')}.pdf`)

}