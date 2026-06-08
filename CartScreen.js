import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, Alert } from 'react-native';
import CartService from '../services/cartService';

export default function CartScreen({ navigation }) {
  const [items, setItems] = useState([]);

  useEffect(() => {
    const unsubscribe = navigation.addListener('focus', () => {
      loadCart();
    });
    loadCart();
    return unsubscribe;
  }, [navigation]);

  const loadCart = async () => {
    const c = await CartService.getCart();
    setItems(c);
  };

  const handleRemove = async (id) => {
    const updated = await CartService.removeItem(id);
    setItems(updated);
  };

  const handleClear = async () => {
    Alert.alert('Clear Cart', 'Are you sure you want to clear the cart?', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Clear', style: 'destructive', onPress: async () => { await CartService.clearCart(); setItems([]); } },
    ]);
  };

  const handleCheckout = async () => {
    // Placeholder: in real app you'd call an API to create a sale/transaction
    Alert.alert('Checkout', 'This is a demo checkout. Cart will be cleared.', [
      { text: 'OK', onPress: async () => { await CartService.clearCart(); setItems([]); } },
    ]);
  };

  const renderItem = ({ item }) => (
    <View style={styles.itemRow}>
      <View>
        <Text style={styles.itemName}>{item.name}</Text>
        <Text style={styles.itemMeta}>₱{item.price} x {item.qty}</Text>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Text style={styles.itemTotal}>₱{(item.price * item.qty).toFixed(2)}</Text>
        <TouchableOpacity style={styles.removeButton} onPress={() => handleRemove(item.id)}>
          <Text style={styles.removeText}>Remove</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  const total = items.reduce((s, it) => s + (it.price || 0) * (it.qty || 1), 0);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Cart</Text>
        <View style={{ flexDirection: 'row' }}>
          <TouchableOpacity style={styles.headerButton} onPress={() => navigation.navigate('ScannerPOS')}>
            <Text style={styles.headerButtonText}>Scan</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.headerButton, { marginLeft: 10 }]} onPress={handleClear}>
            <Text style={styles.headerButtonText}>Clear</Text>
          </TouchableOpacity>
        </View>
      </View>

      <FlatList data={items} keyExtractor={(i) => String(i.id)} renderItem={renderItem} contentContainerStyle={{ padding: 20 }} ListEmptyComponent={<Text style={{ textAlign: 'center', color: '#666' }}>Cart is empty</Text>} />

      <View style={styles.footer}>
        <Text style={styles.totalText}>Total: ₱{total.toFixed(2)}</Text>
        <TouchableOpacity style={styles.checkoutButton} onPress={handleCheckout} disabled={items.length === 0}>
          <Text style={styles.checkoutText}>Checkout</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  header: { backgroundColor: '#dc3545', padding: 20, paddingTop: 50, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  headerTitle: { color: '#fff', fontSize: 20, fontWeight: 'bold' },
  headerButton: { backgroundColor: '#fff', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 6 },
  headerButtonText: { color: '#dc3545', fontWeight: 'bold' },
  itemRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: '#fff', padding: 15, borderRadius: 10, marginBottom: 12 },
  itemName: { fontWeight: 'bold', fontSize: 16 },
  itemMeta: { color: '#666' },
  itemTotal: { fontWeight: 'bold', fontSize: 16 },
  removeButton: { marginTop: 8 },
  removeText: { color: '#dc3545' },
  footer: { padding: 20, borderTopWidth: 1, borderTopColor: '#eee', backgroundColor: '#fff' },
  totalText: { fontSize: 18, fontWeight: 'bold', marginBottom: 12 },
  checkoutButton: { backgroundColor: '#28a745', padding: 14, borderRadius: 10, alignItems: 'center' },
  checkoutText: { color: '#fff', fontWeight: 'bold' },
});
