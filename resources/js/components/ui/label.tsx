import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { cn } from "@/lib/utils"

function RequiredBadge({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        "inline-flex shrink-0 items-center rounded-sm bg-destructive/10 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-destructive ring-1 ring-destructive/20 dark:bg-destructive/15",
        className
      )}
    >
      必須
    </span>
  )
}

function Label({
  className,
  children,
  required = false,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root> & { required?: boolean }) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "inline-flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {children}
      {required && <RequiredBadge />}
    </LabelPrimitive.Root>
  )
}

export { Label, RequiredBadge }
