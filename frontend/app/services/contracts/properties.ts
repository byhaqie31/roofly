import type { Property, PropertyInput, PropertyUpdate } from "~/types/property";

export interface PropertiesService {
  getProperties(): Promise<Property[]>;
  getProperty(id: string): Promise<Property | null>;
  create(input: PropertyInput): Promise<Property>;
  update(id: string, patch: PropertyUpdate): Promise<Property>;
  remove(id: string): Promise<void>;
}
