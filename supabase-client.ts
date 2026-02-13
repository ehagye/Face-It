import { createClient } from "@supabase/supabase-js";

export const supabase = createClient(
    "https://evoqwkezqahsvctmopld.supabase.co",
    "sb_publishable_OzhXMAGzO7kkMhHrOUPbQw_GKrJIPbM"
);

async function testConnection() {
    const { data, error } = await supabase.from("students").select("*");
    console.log(data, error);
}

testConnection();