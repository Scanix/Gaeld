export const subscriptionStatusClass = {
  active: 'text-[hsl(var(--primary))] bg-[hsl(var(--accent))]',
  trialing: 'text-blue-700 bg-blue-50 dark:text-blue-400 dark:bg-blue-950/50',
  past_due: 'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.08)]',
  canceled: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
  paused: 'text-yellow-700 bg-yellow-50 dark:text-yellow-400 dark:bg-yellow-950/50',
}

export const invoiceStatusClass = {
  paid: 'text-[hsl(var(--primary))] bg-[hsl(var(--accent))]',
  open: 'text-yellow-700 bg-yellow-50 dark:text-yellow-400 dark:bg-yellow-950/50',
  uncollectible: 'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.1)]',
  draft: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
  void: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
}
